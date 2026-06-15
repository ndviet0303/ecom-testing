const { test, expect } = require("@playwright/test");
const fs = require("node:fs");
const path = require("node:path");

const toolBaseUrl = process.env.RAG_UI_BASE_URL || "http://127.0.0.1:8765";
const runtimeCollection = path.resolve(__dirname, "../.runtime/uploaded-collection.json");
const collectionFile = process.env.RAG_COLLECTION_FILE || (fs.existsSync(runtimeCollection) ? runtimeCollection : "");
const apiBaseUrl = process.env.RAG_API_BASE_URL || "https://ecom.ziet.dev//api";

test("load selected collection and run API suite smoke flow", async ({ page }) => {
  test.setTimeout(300000);
  test.skip(!collectionFile, "Select a collection in the UI first or set RAG_COLLECTION_FILE.");

  await page.goto(toolBaseUrl);

  await page.click('[data-sidebar-tab="run"]');
  await page.setInputFiles("#run-collection-file", collectionFile);
  await page.fill("#api-base-url", apiBaseUrl);

  await expect(page.locator("#summary-count")).not.toHaveText("-");

  await page.click("#run-api-button");
  await expect(page.locator("#api-total")).not.toHaveText("-", { timeout: 240000 });
  await expect(page.locator("#api-results")).toContainText(/OK|FAIL/, { timeout: 240000 });
});
