"""API tests for /predict and /health."""
import pytest
from fastapi.testclient import TestClient

from main import app, MAX_HISTORICAL_SALES_POINTS

client = TestClient(app)


def test_root():
    r = client.get("/")
    assert r.status_code == 200
    assert r.json() == {"message": "Hola mundo"}


def test_health():
    r = client.get("/health")
    assert r.status_code == 200
    assert r.json() == {"status": "ok"}


def test_predict_empty_historical_sales():
    r = client.post(
        "/predict",
        json={
            "historical_sales": [],
            "lead_time_days": 7,
            "current_stock": 10,
        },
    )
    assert r.status_code == 200
    data = r.json()
    assert "predicted_next_30_days" in data
    assert "predicted_daily_average" in data
    assert "predicted_stock_out_date" in data
    assert "recommended_purchase_quantity" in data
    assert data["predicted_next_30_days"] == 0
    assert data["predicted_daily_average"] == 0
    assert data["predicted_stock_out_date"] is None
    assert data["recommended_purchase_quantity"] >= 0


def test_predict_one_point():
    r = client.post(
        "/predict",
        json={
            "historical_sales": [{"date": "2025-01-15", "quantity": 5}],
            "lead_time_days": 0,
            "current_stock": 0,
        },
    )
    assert r.status_code == 200
    data = r.json()
    assert data["predicted_daily_average"] == 5.0
    assert data["predicted_next_30_days"] == 150.0


def test_predict_two_points_linear():
    r = client.post(
        "/predict",
        json={
            "historical_sales": [
                {"date": "2025-01-01", "quantity": 10},
                {"date": "2025-01-02", "quantity": 20},
            ],
            "lead_time_days": 0,
            "current_stock": 100,
        },
    )
    assert r.status_code == 200
    data = r.json()
    assert data["predicted_daily_average"] >= 0
    assert data["predicted_next_30_days"] >= 0
    assert data["predicted_stock_out_date"] is not None or data["predicted_daily_average"] == 0
    assert "recommended_purchase_quantity" in data


def test_predict_invalid_date_format():
    r = client.post(
        "/predict",
        json={
            "historical_sales": [{"date": "15-01-2025", "quantity": 1}],
            "lead_time_days": 0,
            "current_stock": 0,
        },
    )
    assert r.status_code == 422


def test_predict_invalid_date_value():
    r = client.post(
        "/predict",
        json={
            "historical_sales": [{"date": "2025-13-45", "quantity": 1}],
            "lead_time_days": 0,
            "current_stock": 0,
        },
    )
    assert r.status_code == 422


def test_predict_negative_quantity():
    r = client.post(
        "/predict",
        json={
            "historical_sales": [{"date": "2025-01-01", "quantity": -1}],
            "lead_time_days": 0,
            "current_stock": 0,
        },
    )
    assert r.status_code == 422


def test_predict_too_many_points():
    too_many = [
        {"date": "2025-01-01", "quantity": 1},
    ] * (MAX_HISTORICAL_SALES_POINTS + 1)
    r = client.post(
        "/predict",
        json={
            "historical_sales": too_many,
            "lead_time_days": 0,
            "current_stock": 0,
        },
    )
    assert r.status_code == 422
