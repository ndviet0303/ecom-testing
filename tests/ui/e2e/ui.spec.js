const { test, expect } = require("@playwright/test");
const fs = require("node:fs");
const path = require("node:path");

const siteBaseUrl = process.env.RAG_SITE_BASE_URL || "https://ecom.ziet.dev/";
const runtimeCollection = path.resolve(__dirname, "../.runtime/uploaded-collection.json");
const collectionFile = process.env.RAG_COLLECTION_FILE || (fs.existsSync(runtimeCollection) ? runtimeCollection : "");

const JOURNEY_DEFINITIONS = [
  {
    id: "auth-login",
    route: "/login",
    title: "auth/login UI",
    endpoint: /auth\/login|\/profile|\/wishlist|\/addresses/i,
    waitFor: /\/api\/auth\/login/i,
    assertText: /Đăng nhập|ZIET\.PC|Email|Mật khẩu|checkout|profile/i,
    action: "login",
  },
  {
    id: "auth-register",
    route: "/register",
    title: "auth/register UI",
    endpoint: /auth\/register/i,
    assertText: /Tạo tài khoản|Đăng ký|ZIET\.PC|Email|Mật khẩu/i,
  },
  {
    id: "products",
    route: "/products",
    title: "product catalog UI",
    endpoint: /\/v1\/products|\/products(?:\/|\?|$)/i,
    waitFor: /\/api\/v1\/products/i,
    assertText: /Hardware Showcase|linh kiện|CPU|AMD|Intel|NVIDIA|RTX/i,
    action: "search",
  },
  {
    id: "builder",
    route: "/builder",
    title: "PC builder UI",
    endpoint: /build\/validate|pc-builder|compatibility/i,
    waitFor: /\/api\/v1\/(products|pc-builder|build)/i,
    assertText: /Bộ lắp ráp|Thông minh|Vi xử lý|CPU|GPU|Mainboard/i,
    action: "builderSlot",
  },
  {
    id: "cart",
    route: "/cart",
    title: "cart UI",
    endpoint: /\/cart/i,
    waitFor: /\/api\/v1\/cart|\/api\/cart/i,
    assertText: /Giỏ hàng|Của bạn|trống|checkout|thanh toán/i,
  },
  {
    id: "checkout",
    route: "/checkout",
    title: "checkout UI",
    endpoint: /\/checkout|\/orders|\/coupons|\/shipping-zones|\/payments/i,
    assertText: /Thanh toán|Đăng nhập|Giỏ hàng|Địa chỉ|Checkout|ZIET/i,
    requiresLogin: true,
  },
  {
    id: "admin",
    route: "/admin/orders",
    title: "admin access UI",
    endpoint: /\/admin/i,
    assertText: /Admin|Đơn hàng|Orders|Dashboard|Unauthorized|403|Đăng nhập|Home|PC Builder|Products/i,
    requiresAdmin: true,
  },
];

function loadCollection() {
  test.skip(!collectionFile || !fs.existsSync(collectionFile), "Select a collection in the UI first or set RAG_COLLECTION_FILE.");
  return JSON.parse(fs.readFileSync(collectionFile, "utf8"));
}

function getApis(collection) {
  if (Array.isArray(collection.apis)) return collection.apis;
  if (Array.isArray(collection.items)) return collection.items;
  if (Array.isArray(collection.requests)) return collection.requests;
  return [];
}

function getEndpoint(api) {
  return String(api.endpoint || api.path || api.url || api.name || "");
}

function getTestcases(api) {
  if (Array.isArray(api.testcases)) return api.testcases;
  if (Array.isArray(api.tests)) return api.tests;
  if (Array.isArray(api.cases)) return api.cases;
  return [];
}

function getRequestPayload(testcase) {
  return testcase?.request || testcase?.input || {};
}

function firstValue(apis, predicate, selectors) {
  for (const api of apis) {
    if (!predicate(api)) continue;
    for (const testcase of getTestcases(api)) {
      const request = getRequestPayload(testcase);
      for (const select of selectors) {
        const value = select(request, testcase, api);
        if (value !== undefined && value !== null && String(value).trim()) return String(value);
      }
    }
  }
  return "";
}

function buildUiContext(apis) {
  const productApis = (api) => /\/v1\/products|\/products(?:\/|\?|$)/i.test(getEndpoint(api));
  const loginApis = (api) => /auth\/login/i.test(getEndpoint(api));

  return {
    searchTerm:
      firstValue(productApis.length ? apis : [], productApis, [
        (request) => request.query?.q,
        (request) => request.query?.search,
        (request) => request.query?.category,
        (request, testcase) => testcase.name?.match(/\b(AMD|Intel|NVIDIA|RTX|Ryzen|CPU|GPU)\b/i)?.[0],
      ]) || process.env.RAG_UI_SEARCH_TERM || "AMD",
    customerEmail:
      process.env.RAG_UI_CUSTOMER_EMAIL ||
      firstValue(apis, loginApis, [(request) => request.body?.email, (request) => request.email]) ||
      "customer@ziet.dev",
    customerPassword:
      process.env.RAG_UI_CUSTOMER_PASSWORD ||
      firstValue(apis, loginApis, [(request) => request.body?.password, (request) => request.password]) ||
      "password",
    adminEmail: process.env.RAG_UI_ADMIN_EMAIL || "admin@ziet.dev",
    adminPassword: process.env.RAG_UI_ADMIN_PASSWORD || "password",
  };
}

function compileJourneys(collection) {
  const apis = getApis(collection);
  const matched = new Map();

  for (const api of apis) {
    const endpoint = getEndpoint(api);
    for (const definition of JOURNEY_DEFINITIONS) {
      if (!definition.endpoint.test(endpoint)) continue;
      const existing = matched.get(definition.id) || { ...definition, endpoints: [], apis: [] };
      existing.endpoints.push(endpoint);
      existing.apis.push(api);
      matched.set(definition.id, existing);
    }
  }

  return Array.from(matched.values()).sort((a, b) => {
    const order = JOURNEY_DEFINITIONS.map((definition) => definition.id);
    return order.indexOf(a.id) - order.indexOf(b.id);
  });
}

function siteUrl(route) {
  return new URL(route, siteBaseUrl).toString();
}

async function gotoPage(page, route) {
  await page.goto(siteUrl(route), { waitUntil: "domcontentloaded" });
}

async function waitForApi(page, pattern) {
  return page
    .waitForResponse((response) => pattern.test(response.url()) && response.status() < 500, { timeout: 20000 })
    .catch(() => null);
}

async function fillIfVisible(locator, value) {
  if ((await locator.count()) === 0) return false;
  const first = locator.first();
  if (!(await first.isVisible().catch(() => false))) return false;
  await first.fill(value);
  return true;
}

async function clickIfVisible(locator) {
  if ((await locator.count()) === 0) return false;
  const first = locator.first();
  if (!(await first.isVisible().catch(() => false))) return false;
  await first.click();
  return true;
}

async function clearBrowserState(page) {
  await gotoPage(page, "/");
  await page.evaluate(() => {
    localStorage.clear();
    sessionStorage.clear();
  });
}

async function performLogin(page, context, admin = false) {
  await gotoPage(page, "/login");
  await fillIfVisible(page.locator('input[type="email"], input[name="email"]'), admin ? context.adminEmail : context.customerEmail);
  await fillIfVisible(
    page.locator('input[type="password"], input[name="password"]'),
    admin ? context.adminPassword : context.customerPassword,
  );
  const loginResponse = waitForApi(page, /\/api\/auth\/login/i);
  await clickIfVisible(page.getByRole("button", { name: /Đăng nhập/i }));
  await loginResponse;
}

async function runJourneyAction(page, journey, context) {
  if (journey.requiresAdmin) {
    await clearBrowserState(page);
    await performLogin(page, context, true);
    return;
  }

  if (journey.requiresLogin) {
    await performLogin(page, context, false);
    return;
  }

  if (journey.action === "login") {
    await fillIfVisible(page.locator('input[type="email"], input[name="email"]'), context.customerEmail);
    await fillIfVisible(page.locator('input[type="password"], input[name="password"]'), context.customerPassword);
    const loginResponse = waitForApi(page, /\/api\/auth\/login/i);
    const clicked = await clickIfVisible(page.getByRole("button", { name: /Đăng nhập/i }));
    expect(clicked).toBeTruthy();
    await loginResponse;
  }

  if (journey.action === "search") {
    const searched = await fillIfVisible(page.locator('input[placeholder*="Tìm kiếm"], input[type="search"]').first(), context.searchTerm);
    if (!searched) return;
    await page.waitForTimeout(500);
    await expect(page.locator("body")).toContainText(/AMD|Intel|NVIDIA|RTX|Ryzen|Không tìm thấy|linh kiện/i, { timeout: 30000 });
  }

  if (journey.action === "builderSlot") {
    await clickIfVisible(page.getByText(/Vi xử lý|CPU/i).first());
    await expect(page.locator("body")).toContainText(/CPU|Vi xử lý|Chọn|linh kiện/i, { timeout: 30000 });
  }
}

test("run ecommerce UI journeys compiled from selected collection", async ({ page }) => {
  test.setTimeout(120000);

  const collection = loadCollection();
  const apis = getApis(collection);
  const context = buildUiContext(apis);
  const journeys = compileJourneys(collection);
  const body = page.locator("body");

  test.skip(journeys.length === 0, "Collection does not contain endpoints that map to known ecommerce UI journeys.");

  await test.step("home page loads against target site", async () => {
    await gotoPage(page, "/");
    await expect(page).toHaveTitle(/ZIET|PC|Builder|Ecom/i);
    await expect(body).toContainText(/ZIET|Kiến tạo|Siêu Máy Tính|lắp máy|linh kiện/i, { timeout: 30000 });
  });

  for (const journey of journeys) {
    await test.step(`${journey.title}: ${journey.endpoints.join(", ")}`, async () => {
      const response = journey.waitFor ? waitForApi(page, journey.waitFor) : Promise.resolve(null);
      await gotoPage(page, journey.route);
      await response;
      await expect(body).toContainText(journey.assertText, { timeout: 30000 });
      await runJourneyAction(page, journey, context);
      await gotoPage(page, journey.route);
      await expect(body).toContainText(journey.assertText, { timeout: 30000 });
    });
  }
});
