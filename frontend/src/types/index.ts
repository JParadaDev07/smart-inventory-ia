export interface Business {
  id: number
  name: string
  address?: string
  phone?: string
}

export interface SubscriptionInfo {
  plan: string
  status?: string
  trial_ends_at?: string
  current_period_end?: string
  is_pro: boolean
  is_active?: boolean
}

export interface User {
  id: number
  name: string
  email: string
  business_id: number
  business?: Business
  subscription?: SubscriptionInfo
}

export interface Product {
  id: number
  business_id?: number
  name: string
  sku?: string
  cost_price: number
  sale_price: number
  current_stock: number
  minimum_stock: number
  supplier_lead_time_days: number
  perecedero?: boolean
  expired_mode?: 'bloquear' | 'alerta' | 'permitir' | string
  expiration_alert_days?: number
  created_at?: string
  updated_at?: string
}

export interface Sale {
  id: number
  business_id?: number
  total_amount: number
  created_at: string
  updated_at?: string
  items?: SaleItem[]
  has_expired_items?: boolean
  expired_quantity_total?: number
}

export interface SaleItem {
  id: number
  sale_id: number
  product_id: number
  quantity: number
  unit_price: number
  product?: Product
}

export interface SalesByDay {
  date: string
  total: number
}

export interface TopProduct {
  name: string
  total_qty: number
}

export interface DashboardData {
  total_products: number
  low_stock_products: number
  sales_last_30_days: number
  sales_by_day?: SalesByDay[]
  ai_alerts: number
  top_products?: TopProduct[]
}

export interface IntelligenceData {
  average_daily_demand: number
  safety_stock: number
  reorder_point: number
  recommended_purchase_quantity: number
  estimated_stock_out_date: string | null
}

export interface AiPrediction {
  predicted_next_30_days: number
  predicted_daily_average: number
  predicted_stock_out_date: string | null
  recommended_purchase_quantity: number
  source: 'ai' | 'local'
}

export interface AiPredictionHorizon {
  total: number
  daily_average: number
  stock_out_date: string | null
  recommended_purchase_quantity: number
}

export interface AiPredictionAdvanced {
  [horizon: string]: AiPredictionHorizon | null
}

export interface ProductIntelligenceResponse {
  product: {
    id: number
    name: string
    current_stock: number
    minimum_stock: number
    perecedero?: boolean
    expired_mode?: 'bloquear' | 'alerta' | 'permitir' | string
    expiration_alert_days?: number
  }
  intelligence: IntelligenceData
  ai_prediction?: AiPrediction
  ai_prediction_advanced?: AiPredictionAdvanced
}

export interface SubscriptionData {
  plan: string
  status: string
  trial_ends_at?: string
  current_period_end?: string
  days_remaining: number | null
  trial_days_remaining: number | null
  is_pro: boolean
  is_active: boolean
}

export interface Ticket {
  id: number
  business_id: number
  user_id: number
  subject: string
  description: string
  status: 'open' | 'in_progress' | 'resolved' | string
  priority: 'low' | 'medium' | 'high' | string
  created_at: string
  updated_at: string
}

export interface TicketMessage {
  id: number
  ticket_id: number
  user_id: number | null
  sender_type: 'user' | 'admin' | string
  message: string
  created_at: string
  updated_at: string
}

export interface PaginatedResponse<T> {
  data: T[]
  links?: { first: string; last: string; prev: string | null; next: string | null }
  meta?: { current_page: number; last_page: number; per_page: number; total: number }
}
