import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate } from 'k6/metrics';

export const options = {
  scenarios: {
    one_hundred_users_all_operations: {
      executor: 'ramping-vus',
      stages: [
        { duration: '20s', target: 25 },
        { duration: '20s', target: 50 },
        { duration: '20s', target: 100 },
        { duration: '60s', target: 100 },
        { duration: '20s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.30'],
    http_req_duration: ['p(95)<5000'],
    checks: ['rate>0.80'],
    server_errors_500: ['count==0'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000/api';
const TOKEN = __ENV.TOKEN || '';
const ADMIN_TOKEN = __ENV.ADMIN_TOKEN || TOKEN;
const PHONE = __ENV.PHONE || '';
const PASSWORD = __ENV.PASSWORD || '';
const PRODUCT_ID = __ENV.PRODUCT_ID || '1';
const CART_ID = __ENV.CART_ID || '1';
const CART_ITEM_ID = __ENV.CART_ITEM_ID || '1';
const ORDER_ID = __ENV.ORDER_ID || '1';
const ORDER_ITEM_ID = __ENV.ORDER_ITEM_ID || '1';
const PAYMENT_ID = __ENV.PAYMENT_ID || '1';
const INVOICE_ID = __ENV.INVOICE_ID || '1';
const NOTIFICATION_ID = __ENV.NOTIFICATION_ID || '1';
const BENCHMARK_ID = __ENV.BENCHMARK_ID || '1';
const SERVER_NODE_ID = __ENV.SERVER_NODE_ID || '1';
const REQUEST_LOG_ID = __ENV.REQUEST_LOG_ID || '1';
const DAILY_REPORT_ID = __ENV.DAILY_REPORT_ID || '1';
const DAILY_REPORT_ITEM_ID = __ENV.DAILY_REPORT_ITEM_ID || '1';
const REPORT_DATE = __ENV.REPORT_DATE || new Date().toISOString().slice(0, 10);

const serverErrors500 = new Counter('server_errors_500');
const capacityRejected = new Counter('capacity_rejected');
const conflictResponses = new Counter('conflict_responses');
const validationResponses = new Counter('validation_responses');
const unauthorizedResponses = new Counter('unauthorized_or_forbidden_responses');
const successfulRequests = new Counter('successful_requests');
const acceptableResponseRate = new Rate('acceptable_response_rate');

function headers(token = TOKEN) {
  const h = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };

  if (token && token.length > 10) {
    h.Authorization = `Bearer ${token}`;
  }

  return h;
}

function unique(prefix) {
  return `${prefix}-${__VU}-${__ITER}-${Date.now()}`;
}

function record(res) {
  const acceptableStatuses = [200, 201, 202, 204, 400, 401, 403, 404, 409, 422, 429, 503];
  const acceptable = acceptableStatuses.includes(res.status);

  acceptableResponseRate.add(acceptable);
  if (res.status >= 200 && res.status < 300) successfulRequests.add(1);
  if (res.status === 500) serverErrors500.add(1);
  if (res.status === 409) conflictResponses.add(1);
  if (res.status === 422) validationResponses.add(1);
  if (res.status === 429 || res.status === 503) capacityRejected.add(1);
  if (res.status === 401 || res.status === 403) unauthorizedResponses.add(1);

  check(res, {
    'server answered': (r) => r.status !== 0,
    'no 500 error': (r) => r.status !== 500,
    'acceptable status': () => acceptable,
    'response under 5s': (r) => r.timings.duration < 5000,
  });
}

function request(method, path, body = null, token = TOKEN, name = path) {
  const params = { headers: headers(token), tags: { name } };
  let res;

  if (method === 'GET') res = http.get(`${BASE_URL}${path}`, params);
  if (method === 'POST') res = http.post(`${BASE_URL}${path}`, body === null ? null : JSON.stringify(body), params);
  if (method === 'PUT') res = http.put(`${BASE_URL}${path}`, body === null ? null : JSON.stringify(body), params);
  if (method === 'PATCH') res = http.patch(`${BASE_URL}${path}`, body === null ? null : JSON.stringify(body), params);
  if (method === 'DELETE') res = http.del(`${BASE_URL}${path}`, null, params);

  record(res);
  return res;
}

const operations = [
  () => request('GET', '/', null, TOKEN, 'GET /'),
  () => request('GET', '/products', null, TOKEN, 'GET /products'),
  () => request('GET', `/products/${PRODUCT_ID}`, null, TOKEN, 'GET /products/{product}'),
  () => request('GET', '/products/most-ordered', null, TOKEN, 'GET /products/most-ordered'),

  () => PHONE && PASSWORD
    ? request('POST', '/auth/login', { phone: PHONE, password: PASSWORD }, '', 'POST /auth/login')
    : request('POST', '/auth/register', {
        name: unique('stress-user'),
        phone: unique('09'),
        password: 'password123',
        password_confirmation: 'password123',
        role: 'user',
      }, '', 'POST /auth/register'),

  () => request('GET', '/wallet', null, TOKEN, 'GET /wallet'),
  () => request('POST', '/wallet/deposit', { amount: 10 }, TOKEN, 'POST /wallet/deposit'),

  () => request('GET', '/carts', null, TOKEN, 'GET /carts'),
  () => request('POST', '/carts', { status: 'open' }, TOKEN, 'POST /carts'),
  () => request('GET', `/carts/${CART_ID}`, null, TOKEN, 'GET /carts/{cart}'),
  () => request('PUT', `/carts/${CART_ID}`, { status: 'open' }, TOKEN, 'PUT /carts/{cart}'),

  () => request('GET', '/cart-items', null, TOKEN, 'GET /cart-items'),
  () => request('POST', '/cart-items', {
    cart_id: Number(CART_ID),
    product_id: Number(PRODUCT_ID),
    product_name: 'Stress Product',
    quantity: 1,
    unit_price: 1,
  }, TOKEN, 'POST /cart-items'),
  () => request('GET', `/cart-items/${CART_ITEM_ID}`, null, TOKEN, 'GET /cart-items/{cartItem}'),
  () => request('PUT', `/cart-items/${CART_ITEM_ID}`, { quantity: 1 }, TOKEN, 'PUT /cart-items/{cartItem}'),

  () => request('GET', '/orders', null, TOKEN, 'GET /orders'),
  () => request('POST', '/orders', {
    cart_id: Number(CART_ID),
    status: 'pending',
    payment_status: 'unpaid',
    notes: 'stress test order',
  }, TOKEN, 'POST /orders'),
  () => request('GET', `/orders/${ORDER_ID}`, null, TOKEN, 'GET /orders/{order}'),
  () => request('PUT', `/orders/${ORDER_ID}`, { notes: unique('stress-update') }, TOKEN, 'PUT /orders/{order}'),
  () => request('POST', `/orders/${ORDER_ID}/checkout`, {
    payment_method: 'wallet',
    idempotency_key: unique('idem'),
  }, TOKEN, 'POST /orders/{order}/checkout'),

  () => request('GET', '/order-items', null, TOKEN, 'GET /order-items'),
  () => request('POST', '/order-items', {
    order_id: Number(ORDER_ID),
    product_id: Number(PRODUCT_ID),
    product_name: 'Stress Product',
    quantity: 1,
    unit_price: 1,
  }, TOKEN, 'POST /order-items'),
  () => request('GET', `/order-items/${ORDER_ITEM_ID}`, null, TOKEN, 'GET /order-items/{orderItem}'),
  () => request('PUT', `/order-items/${ORDER_ITEM_ID}`, { quantity: 1 }, TOKEN, 'PUT /order-items/{orderItem}'),

  () => request('GET', '/payments', null, TOKEN, 'GET /payments'),
  () => request('POST', '/payments', {
    order_id: Number(ORDER_ID),
    payment_method: 'wallet',
    idempotency_key: unique('payment'),
    amount: 1,
    status: 'completed',
  }, TOKEN, 'POST /payments'),
  () => request('GET', `/payments/${PAYMENT_ID}`, null, TOKEN, 'GET /payments/{payment}'),

  () => request('GET', '/invoices', null, TOKEN, 'GET /invoices'),
  () => request('POST', '/invoices', { order_id: Number(ORDER_ID), status: 'issued' }, TOKEN, 'POST /invoices'),
  () => request('GET', `/invoices/${INVOICE_ID}`, null, TOKEN, 'GET /invoices/{invoice}'),

  () => request('GET', '/notifications', null, TOKEN, 'GET /notifications'),
  () => request('POST', '/notifications', {
    user_id: 1,
    order_id: Number(ORDER_ID),
    type: 'stress-test',
    message: 'Stress test notification',
    status: 'pending',
  }, TOKEN, 'POST /notifications'),
  () => request('GET', `/notifications/${NOTIFICATION_ID}`, null, TOKEN, 'GET /notifications/{notification}'),

  () => request('GET', '/daily-sales-reports', null, TOKEN, 'GET /daily-sales-reports'),
  () => request('POST', '/daily-sales-reports/generate', { report_date: REPORT_DATE }, TOKEN, 'POST /daily-sales-reports/generate'),
  () => request('POST', '/daily-sales-reports', { report_date: REPORT_DATE }, TOKEN, 'POST /daily-sales-reports'),
  () => request('GET', `/daily-sales-reports/${DAILY_REPORT_ID}`, null, TOKEN, 'GET /daily-sales-reports/{dailySalesReport}'),

  () => request('GET', '/daily-sales-report-items', null, TOKEN, 'GET /daily-sales-report-items'),
  () => request('POST', '/daily-sales-report-items', {
    daily_sales_report_id: Number(DAILY_REPORT_ID),
    product_id: Number(PRODUCT_ID),
    total_quantity_sold: 1,
    total_revenue: 1,
    inventory_movements: 1,
    product_rank: 1,
  }, TOKEN, 'POST /daily-sales-report-items'),
  () => request('GET', `/daily-sales-report-items/${DAILY_REPORT_ITEM_ID}`, null, TOKEN, 'GET /daily-sales-report-items/{dailySalesReportItem}'),

  () => request('GET', '/benchmark-results', null, TOKEN, 'GET /benchmark-results'),
  () => request('POST', '/benchmark-results', {
    operation_name: 'all-operations-stress',
    scenario: '100 concurrent users',
    concurrent_users: 100,
    total_requests: 1,
    successful_requests: 1,
    failed_requests: 0,
    average_response_time_ms: 1,
    max_response_time_ms: 1,
    throughput_per_second: 1,
    bottleneck_note: 'Generated by k6 stress script',
    optimization_applied: 'Redis cache + distributed locks',
  }, TOKEN, 'POST /benchmark-results'),
  () => request('GET', `/benchmark-results/${BENCHMARK_ID}`, null, TOKEN, 'GET /benchmark-results/{benchmarkResult}'),

  () => request('GET', '/server-nodes', null, TOKEN, 'GET /server-nodes'),
  () => request('POST', '/server-nodes', {
    name: unique('node'),
    host: '127.0.0.1',
    max_concurrent_requests: 100,
    current_load: 0,
  }, ADMIN_TOKEN, 'POST /server-nodes'),
  () => request('GET', `/server-nodes/${SERVER_NODE_ID}`, null, TOKEN, 'GET /server-nodes/{serverNode}'),
  () => request('PUT', `/server-nodes/${SERVER_NODE_ID}`, { current_load: 1 }, ADMIN_TOKEN, 'PUT /server-nodes/{serverNode}'),

  () => request('GET', '/request-logs', null, TOKEN, 'GET /request-logs'),
  () => request('POST', '/request-logs', {
    server_node_id: Number(SERVER_NODE_ID),
    operation_name: 'stress-test',
    endpoint: '/api/stress-test',
    method: 'GET',
    response_time_ms: 1,
    status_code: 200,
  }, TOKEN, 'POST /request-logs'),
  () => request('GET', `/request-logs/${REQUEST_LOG_ID}`, null, TOKEN, 'GET /request-logs/{requestLog}'),
];

export default function () {
  const operation = operations[Math.floor(Math.random() * operations.length)];
  operation();
  sleep(0.2);
}

export function handleSummary(data) {
  return {
    'stress-test-100-all-operations-summary.json': JSON.stringify(data, null, 2),
    stdout: `
========================================
100 Users - All Operations Stress Summary
========================================
Total Requests: ${data.metrics.http_reqs?.values?.count || 0}
Failed Rate: ${data.metrics.http_req_failed?.values?.rate || 0}
Average Response Time: ${data.metrics.http_req_duration?.values?.avg || 0} ms
P95 Response Time: ${data.metrics.http_req_duration?.values?.['p(95)'] || 0} ms

Successful Requests: ${data.metrics.successful_requests?.values?.count || 0}
500 Server Errors: ${data.metrics.server_errors_500?.values?.count || 0}
409 Conflicts: ${data.metrics.conflict_responses?.values?.count || 0}
422 Validation Responses: ${data.metrics.validation_responses?.values?.count || 0}
401/403 Responses: ${data.metrics.unauthorized_or_forbidden_responses?.values?.count || 0}
429/503 Capacity Rejections: ${data.metrics.capacity_rejected?.values?.count || 0}
Acceptable Response Rate: ${data.metrics.acceptable_response_rate?.values?.rate || 0}

Notes:
- 500 must stay 0.
- 409 shows concurrency/checkout protection is working.
- 429 or 503 shows capacity control is working.
- 401/403 may appear if TOKEN/ADMIN_TOKEN are not provided.
========================================
`,
  };
}
