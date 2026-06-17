import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

export const options = {
  scenarios: {
    three_hundred_users_test: {
      executor: 'ramping-vus',
      stages: [
        { duration: '15s', target: 50 },
        { duration: '20s', target: 150 },
        { duration: '25s', target: 300 },
        { duration: '30s', target: 300 },
        { duration: '15s', target: 0 },
      ],
    },
  },

  thresholds: {
    http_req_failed: ['rate<0.20'],
    http_req_duration: ['p(95)<4000'],
    checks: ['rate>0.80'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000/api';
const TOKEN = __ENV.TOKEN || '';
const ORDER_ID = __ENV.ORDER_ID || '1';

const serverErrors500 = new Counter('server_errors_500');
const checkoutConflicts409 = new Counter('checkout_conflicts_409');
const capacityRejected429 = new Counter('capacity_rejected_429');
const validationErrors422 = new Counter('validation_errors_422');
const successfulRequests = new Counter('successful_requests');
const connectedRequests = new Counter('connected_requests');

function headers() {
  const h = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };

  if (TOKEN && TOKEN.length > 10) {
    h.Authorization = `Bearer ${TOKEN}`;
  }

  return h;
}

function record(res) {
  if (res.status !== 0) connectedRequests.add(1);

  if (res.status >= 200 && res.status < 300) successfulRequests.add(1);
  if (res.status === 500) serverErrors500.add(1);
  if (res.status === 409) checkoutConflicts409.add(1);
  if (res.status === 429) capacityRejected429.add(1);
  if (res.status === 422) validationErrors422.add(1);
}

export default function () {
  const random = Math.random();
  let res;

  // 50% قراءة المنتجات
  if (random < 0.50) {
    res = http.get(`${BASE_URL}/products`, {
      headers: headers(),
      tags: { name: 'GET /products' },
    });

  // 20% المنتجات الأكثر طلبا
  } else if (random < 0.70) {
    res = http.get(`${BASE_URL}/products/most-ordered`, {
      headers: headers(),
      tags: { name: 'GET /products/most-ordered' },
    });

  // 20% Checkout
  } else if (random < 0.90) {
    res = http.post(`${BASE_URL}/orders/${ORDER_ID}/checkout`, null, {
      headers: headers(),
      tags: { name: 'POST /orders/{order}/checkout' },
    });

  // 10% توليد تقرير المبيعات اليومي
  } else {
    res = http.post(`${BASE_URL}/daily-sales-reports/generate`, null, {
      headers: headers(),
      tags: { name: 'POST /daily-sales-reports/generate' },
    });
  }

  record(res);

  check(res, {
    'request reached server': (r) => r.status !== 0,
    'no 500 server error': (r) => r.status !== 500,
    'allowed status': (r) =>
      [200, 201, 202, 400, 401, 403, 404, 409, 422, 429].includes(r.status),
    'response under 4s': (r) => r.timings.duration < 4000,
  });

  sleep(0.2);
}

export function handleSummary(data) {
  return {
    'stress-test-300-summary.json': JSON.stringify(data, null, 2),
    stdout: `
==============================
300 Users Stress Test Summary
==============================

Total Requests: ${data.metrics.http_reqs?.values?.count || 0}
Failed Rate: ${data.metrics.http_req_failed?.values?.rate || 0}
Average Response Time: ${data.metrics.http_req_duration?.values?.avg || 0} ms
P95 Response Time: ${data.metrics.http_req_duration?.values?.['p(95)'] || 0} ms

Connected Requests: ${data.metrics.connected_requests?.values?.count || 0}
Successful Requests: ${data.metrics.successful_requests?.values?.count || 0}
500 Server Errors: ${data.metrics.server_errors_500?.values?.count || 0}
409 Checkout Conflicts: ${data.metrics.checkout_conflicts_409?.values?.count || 0}
429 Capacity Rejected: ${data.metrics.capacity_rejected_429?.values?.count || 0}
422 Validation Errors: ${data.metrics.validation_errors_422?.values?.count || 0}

Interpretation:
- 500 Errors must be 0.
- 409 means checkout conflict or stock protection is working.
- 429 means capacity control is working.
- High failed rate usually means local php artisan serve reached its limit.
- P95 under 4000ms is acceptable for 300 users on local testing.
==============================
`,
  };
}
