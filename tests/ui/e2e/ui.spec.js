const { test, expect } = require("@playwright/test");

const baseUrl = process.env.RAG_UI_BASE_URL || "http://127.0.0.1:8765";
const collectionFile = process.env.RAG_COLLECTION_FILE;

test("load collection and run tester smoke flow", async ({ page }) => {
  test.skip(!collectionFile, "RAG_COLLECTION_FILE is required for smoke flow.");

  await page.goto(baseUrl);

  await page.setInputFiles("#collection-file", collectionFile);
  await page.click("#load-collection-button");

  await expect(page.locator("#summary-count")).not.toHaveText("-");
  await expect(page.locator("#collection-preview")).toContainText("/");

  await page.click("#scan-button");
  await expect(page.locator("#routes-preview")).toContainText("/");
});
