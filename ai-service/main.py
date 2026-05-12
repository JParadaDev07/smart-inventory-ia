"""
Smart Inventory AI - FastAPI prediction service.
No database, no auth. Pure calculation only.
"""
import logging
import re
import time
from contextlib import asynccontextmanager
from typing import Any

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field, field_validator, model_validator

from services.predict import PredictionService

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("ai-service")

# Max number of historical_sales points to avoid timeouts and memory issues
MAX_HISTORICAL_SALES_POINTS = 2000

predictor = PredictionService()


@asynccontextmanager
async def lifespan(app: FastAPI):
    yield
    predictor.cleanup()


app = FastAPI(
    title="Smart Inventory AI",
    description="Prediction API for inventory. No DB, no auth.",
    version="1.0.0",
    lifespan=lifespan,
)

# YYYY-MM-DD
DATE_PATTERN = re.compile(r"^\d{4}-\d{2}-\d{2}$")


class HistoricalSaleItem(BaseModel):
    date: str = Field(..., description="YYYY-MM-DD")
    quantity: float = Field(..., ge=0)

    @field_validator("date")
    @classmethod
    def date_format(cls, v: str) -> str:
        if not DATE_PATTERN.match(v):
            raise ValueError("date must be YYYY-MM-DD")
        # Check it's a valid calendar date
        from datetime import datetime as dt
        try:
            dt.strptime(v, "%Y-%m-%d")
        except ValueError:
            raise ValueError("date must be a valid calendar date")
        return v


class PredictRequest(BaseModel):
    historical_sales: list[HistoricalSaleItem] = Field(
        default_factory=list,
        description=f"Max {MAX_HISTORICAL_SALES_POINTS} points",
    )
    lead_time_days: int = Field(0, ge=0)
    current_stock: float = Field(0, ge=0)

    @model_validator(mode="after")
    def check_historical_sales_length(self) -> "PredictRequest":
        if len(self.historical_sales) > MAX_HISTORICAL_SALES_POINTS:
            raise ValueError(f"historical_sales must have at most {MAX_HISTORICAL_SALES_POINTS} points")
        return self


class PredictResponse(BaseModel):
    predicted_next_30_days: float
    predicted_daily_average: float
    predicted_stock_out_date: str | None
    recommended_purchase_quantity: float


class HorizonPrediction(BaseModel):
    total: float
    daily_average: float
    stock_out_date: str | None
    recommended_purchase_quantity: float


class PredictAdvancedResponse(BaseModel):
    horizons: dict[str, HorizonPrediction]


@app.get("/")
def root() -> dict[str, str]:
    return {"message": "Hola mundo"}


@app.post("/predict", response_model=PredictResponse)
def predict(payload: PredictRequest) -> dict[str, Any]:
    start = time.perf_counter()
    try:
        result = predictor.predict(
            historical_sales=[{"date": s.date, "quantity": s.quantity} for s in payload.historical_sales],
            lead_time_days=payload.lead_time_days,
            current_stock=payload.current_stock,
        )
        elapsed_ms = (time.perf_counter() - start) * 1000
        logger.info("predict ok n=%s elapsed_ms=%.0f", len(payload.historical_sales), elapsed_ms)
        return result
    except Exception as e:
        elapsed_ms = (time.perf_counter() - start) * 1000
        logger.exception("predict failed n=%s elapsed_ms=%.0f error=%s", len(payload.historical_sales), elapsed_ms, e)
        raise HTTPException(status_code=500, detail="Prediction failed. Please try again or use fewer data points.")


@app.post("/predict/advanced", response_model=PredictAdvancedResponse)
def predict_advanced(payload: PredictRequest) -> dict[str, Any]:
    """
    Pro-only endpoint: returns predictions for multiple horizons (7/30/90 days).
    """
    start = time.perf_counter()
    try:
        horizons: dict[str, Any] = {}
        for h in (7, 30, 90):
            core = predictor.predict_for_horizon(
                historical_sales=[{"date": s.date, "quantity": s.quantity} for s in payload.historical_sales],
                lead_time_days=payload.lead_time_days,
                current_stock=payload.current_stock,
                horizon_days=h,
            )
            horizons[str(h)] = {
                "total": round(core["total"], 2),
                "daily_average": round(core["daily_average"], 2),
                "stock_out_date": core["stock_out_date"],
                "recommended_purchase_quantity": round(core["recommended_purchase_quantity"], 2),
            }
        elapsed_ms = (time.perf_counter() - start) * 1000
        logger.info("predict_advanced ok n=%s elapsed_ms=%.0f", len(payload.historical_sales), elapsed_ms)
        return {"horizons": horizons}
    except Exception as e:
        elapsed_ms = (time.perf_counter() - start) * 1000
        logger.exception(
            "predict_advanced failed n=%s elapsed_ms=%.0f error=%s",
            len(payload.historical_sales),
            elapsed_ms,
            e,
        )
        raise HTTPException(status_code=500, detail="Advanced prediction failed. Please try again or use fewer data points.")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}
