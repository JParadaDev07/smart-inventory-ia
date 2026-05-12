"""
Prediction logic: Prophet if >60 data points, else Linear Regression.
No DB, no auth. Pure calculation.
"""
import logging
from datetime import datetime, timedelta
from typing import Any

logger = logging.getLogger("ai-service")

PROPHET_MIN_POINTS = 60


class PredictionService:
    def __init__(self) -> None:
        self._prophet_model = None

    def predict(
        self,
        historical_sales: list[dict[str, Any]],
        lead_time_days: int,
        current_stock: float,
    ) -> dict[str, Any]:
        """
        Backwards-compatible prediction API used by existing clients.

        Always returns a 30‑day horizon with fixed keys so we don't break the
        FastAPI contract. Advanced horizons are handled by predict_for_horizon().
        """
        core = self.predict_for_horizon(
            historical_sales=historical_sales,
            lead_time_days=lead_time_days,
            current_stock=current_stock,
            horizon_days=30,
        )
        return {
            "predicted_next_30_days": round(core["total"], 2),
            "predicted_daily_average": round(core["daily_average"], 2),
            "predicted_stock_out_date": core["stock_out_date"],
            "recommended_purchase_quantity": round(core["recommended_purchase_quantity"], 2),
        }

    def predict_for_horizon(
        self,
        historical_sales: list[dict[str, Any]],
        lead_time_days: int,
        current_stock: float,
        horizon_days: int,
    ) -> dict[str, Any]:
        """
        Core prediction used for any horizon length (7/30/90 days, etc.).

        Returns a generic structure that higher layers can format as needed.
        """
        if not historical_sales:
            return self._empty_core_response(current_stock)

        daily_avg = self._daily_average(historical_sales)
        try:
            if len(historical_sales) >= PROPHET_MIN_POINTS:
                predicted_30, predicted_daily = self._predict_prophet(historical_sales)
            else:
                predicted_30, predicted_daily = self._predict_linear_regression(historical_sales)
            if predicted_daily <= 0:
                predicted_daily = daily_avg
                predicted_30 = daily_avg * 30
        except Exception as e:
            logger.warning("Model prediction failed, using daily average: %s", e)
            predicted_daily = daily_avg
            predicted_30 = daily_avg * 30

        predicted_30 = max(0, predicted_30)
        predicted_daily = max(0, predicted_daily)

        # Scale total demand to requested horizon.
        scale = max(horizon_days, 0) / 30 if horizon_days > 0 else 0
        predicted_total_horizon = predicted_30 * scale

        # Stock-out date: when current_stock runs out at predicted_daily rate
        if predicted_daily > 0 and current_stock > 0:
            days_until_out = current_stock / predicted_daily
            stock_out_date = (datetime.utcnow() + timedelta(days=days_until_out)).strftime("%Y-%m-%d")
        else:
            stock_out_date = None

        # Reorder: demand over lead time + buffer minus current
        demand_lead_time = predicted_daily * max(lead_time_days, 0)
        safety = predicted_daily * 3
        reorder_point = demand_lead_time + safety
        recommended = max(0, reorder_point - current_stock)

        return {
            "total": predicted_total_horizon,
            "daily_average": predicted_daily,
            "stock_out_date": stock_out_date,
            "recommended_purchase_quantity": recommended,
        }

    def _daily_average(self, historical_sales: list[dict]) -> float:
        total = sum(s["quantity"] for s in historical_sales)
        if not historical_sales:
            return 0.0
        dates = {s["date"] for s in historical_sales}
        days = max(len(dates), 1)
        return total / days

    def _predict_linear_regression(self, historical_sales: list[dict]) -> tuple[float, float]:
        try:
            from sklearn.linear_model import LinearRegression
        except ImportError:
            daily = self._daily_average(historical_sales)
            return daily * 30, daily

        import pandas as pd
        df = pd.DataFrame(historical_sales)
        df["date"] = pd.to_datetime(df["date"], errors="coerce")
        df = df.dropna(subset=["date"])
        if df.empty:
            return 0.0, 0.0
        df["quantity"] = pd.to_numeric(df["quantity"], errors="coerce").fillna(0)
        df = df.sort_values("date").groupby("date", as_index=False)["quantity"].sum()
        if len(df) < 2:
            daily = float(df["quantity"].iloc[0]) if len(df) == 1 else 0.0
            return daily * 30, daily

        df["days"] = (df["date"] - df["date"].min()).dt.days
        X = df[["days"]].values
        y = df["quantity"].values
        model = LinearRegression().fit(X, y)
        last_day = int(df["days"].iloc[-1])
        next_30_days = sum(
            model.predict([[last_day + i]])[0] for i in range(1, 31)
        )
        return max(0, next_30_days), max(0, next_30_days / 30)

    def _predict_prophet(self, historical_sales: list[dict]) -> tuple[float, float]:
        try:
            import pandas as pd
            from prophet import Prophet
        except ImportError:
            total = sum(s["quantity"] for s in historical_sales)
            days = max(len(historical_sales), 1)
            daily = total / days
            return daily * 30, daily

        df = pd.DataFrame(historical_sales)
        df["date"] = pd.to_datetime(df["date"], errors="coerce")
        df = df.dropna(subset=["date"])
        if df.empty:
            return 0.0, 0.0
        df["quantity"] = pd.to_numeric(df["quantity"], errors="coerce").fillna(0)
        df = df.rename(columns={"date": "ds", "quantity": "y"})
        df = df.sort_values("ds").groupby("ds", as_index=False)["y"].sum()
        if len(df) < 2:
            daily = float(df["y"].iloc[0]) if len(df) == 1 else 0.0
            return daily * 30, daily

        model = Prophet(daily_seasonality=False, yearly_seasonality=False, weekly_seasonality=True)
        model.fit(df)

        future = model.make_future_dataframe(periods=30)
        forecast = model.predict(future)
        pred = forecast.tail(30)["yhat"]
        predicted_30 = float(pred.sum())
        predicted_daily = predicted_30 / 30 if predicted_30 else 0.0
        return predicted_30, predicted_daily

    def _empty_core_response(self, current_stock: float) -> dict[str, Any]:
        return {
            "total": 0.0,
            "daily_average": 0.0,
            "stock_out_date": None,
            "recommended_purchase_quantity": 0.0,
        }

    def cleanup(self) -> None:
        self._prophet_model = None
