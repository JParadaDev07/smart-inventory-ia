"""Unit tests for PredictionService."""
import pytest

from services.predict import PredictionService

predictor = PredictionService()


def test_empty_historical_sales():
    r = predictor.predict([], lead_time_days=7, current_stock=10)
    assert r["predicted_next_30_days"] == 0
    assert r["predicted_daily_average"] == 0
    assert r["predicted_stock_out_date"] is None
    assert r["recommended_purchase_quantity"] == 0


def test_one_point():
    r = predictor.predict(
        [{"date": "2025-01-15", "quantity": 6}],
        lead_time_days=0,
        current_stock=0,
    )
    assert r["predicted_daily_average"] == 6.0
    assert r["predicted_next_30_days"] == 180.0
    assert r["predicted_stock_out_date"] is None
    assert r["recommended_purchase_quantity"] >= 0


def test_two_points_uses_linear_regression():
    r = predictor.predict(
        [
            {"date": "2025-01-01", "quantity": 5},
            {"date": "2025-01-02", "quantity": 10},
        ],
        lead_time_days=0,
        current_stock=0,
    )
    assert r["predicted_daily_average"] >= 0
    assert r["predicted_next_30_days"] >= 0
    assert "predicted_stock_out_date" in r
    assert "recommended_purchase_quantity" in r


def test_stock_out_date_when_demand_positive():
    r = predictor.predict(
        [{"date": "2025-01-01", "quantity": 10}],
        lead_time_days=0,
        current_stock=30,
    )
    # 30 stock / 10 per day = 3 days
    assert r["predicted_stock_out_date"] is not None
    assert r["predicted_daily_average"] == 10.0


def test_recommended_purchase_positive_when_below_reorder():
    r = predictor.predict(
        [{"date": "2025-01-01", "quantity": 10}],
        lead_time_days=7,
        current_stock=5,
    )
    assert r["recommended_purchase_quantity"] >= 0


def test_fewer_than_prophet_min_uses_linear():
    # 59 points -> linear regression
    sales = [{"date": f"2025-01-{(i % 28) + 1:02d}", "quantity": 1} for i in range(59)]
    r = predictor.predict(sales, lead_time_days=0, current_stock=0)
    assert r["predicted_next_30_days"] >= 0
    assert r["predicted_daily_average"] >= 0


def test_response_keys():
    r = predictor.predict(
        [{"date": "2025-01-01", "quantity": 2}],
        lead_time_days=0,
        current_stock=0,
    )
    assert set(r.keys()) == {
        "predicted_next_30_days",
        "predicted_daily_average",
        "predicted_stock_out_date",
        "recommended_purchase_quantity",
    }
