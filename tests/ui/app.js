const backendInput = document.querySelector("#backend-src");
const frontendInput = document.querySelector("#frontend-src");
const outputInput = document.querySelector("#output-file");
const limitInput = document.querySelector("#limit");
const dryRunInput = document.querySelector("#dry-run");
const collectionFileInput = document.querySelector("#collection-file");
const loadCollectionButton = document.querySelector("#load-collection-button");
const apiBaseUrlInput = document.querySelector("#api-base-url");
const runApiButton = document.querySelector("#run-api-button");
const runUiButton = document.querySelector("#run-ui-button");
const scanButton = document.querySelector("#scan-button");
const generateButton = document.querySelector("#generate-button");
const actionLog = document.querySelector("#action-log");
const outputPath = document.querySelector("#output-path");
const routesPreview = document.querySelector("#routes-preview");
const collectionPreview = document.querySelector("#collection-preview");
const apiResults = document.querySelector("#api-results");
const uiResults = document.querySelector("#ui-results");
const summaryProject = document.querySelector("#summary-project");
const summaryCount = document.querySelector("#summary-count");
const summaryTime = document.querySelector("#summary-time");
const apiTotal = document.querySelector("#api-total");
const apiPassed = document.querySelector("#api-passed");
const apiFailed = document.querySelector("#api-failed");

async function apiFetch(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      ...(options.headers || {}),
    },
  });
  const rawText = await response.text();
  const contentType = response.headers.get("content-type") || "";

  if (!contentType.includes("application/json")) {
    throw new Error(
      `Endpoint ${url} không trả JSON. Có thể bạn đang mở nhầm instance UI cũ. Status ${response.status}.`,
    );
  }

  let data;
  try {
    data = JSON.parse(rawText);
  } catch {
    throw new Error(`Không parse được JSON từ ${url}.`);
  }

  if (!response.ok) {
    throw new Error(data.message || `Request tới ${url} thất bại với status ${response.status}.`);
  }

  return data;
}

function setBusy(isBusy) {
  scanButton.disabled = isBusy;
  generateButton.disabled = isBusy;
  loadCollectionButton.disabled = isBusy;
  runApiButton.disabled = isBusy;
  runUiButton.disabled = isBusy;
}

function updateLog(message) {
  actionLog.textContent = message;
}

function methodClass(method) {
  return String(method || "").toLowerCase();
}

function renderRoutes(routes = []) {
  if (!routes.length) {
    routesPreview.className = "scroll-panel empty-state";
    routesPreview.textContent = "Chưa có route để hiển thị.";
    return;
  }

  routesPreview.className = "scroll-panel";
  routesPreview.innerHTML = routes
    .map(
      (route) => `
        <div class="route-item">
          <div><span class="pill ${methodClass(route.method)}">${route.method}</span><code>${route.path}</code></div>
          <div class="muted-line">${route.controller || "Closure"} :: ${route.action || "-"}</div>
        </div>
      `,
    )
    .join("");
}

function renderCollection(apis = []) {
  if (!apis.length) {
    collectionPreview.className = "scroll-panel empty-state";
    collectionPreview.textContent = "Chưa có collection để preview.";
    return;
  }

  collectionPreview.className = "scroll-panel";
  collectionPreview.innerHTML = apis
    .map(
      (api) => `
        <div class="api-preview-item">
          <div><span class="pill ${methodClass(api.method)}">${api.method}</span><code>${api.endpoint}</code></div>
          <div class="muted-line">Testcases: ${api.testcases?.length || 0}</div>
          <div class="muted-line">Context: ${api.retrieved_context?.length || 0}</div>
        </div>
      `,
    )
    .join("");
}

function renderSummary(summary, path) {
  summaryProject.textContent = summary?.project || "-";
  summaryCount.textContent = summary?.api_count ?? "-";
  summaryTime.textContent = summary?.generated_at || "-";
  outputPath.textContent = path || "-";
}

function renderApiSummary(summary) {
  apiTotal.textContent = summary?.total ?? "-";
  apiPassed.textContent = summary?.passed ?? "-";
  apiFailed.textContent = summary?.failed ?? "-";
}

function renderApiResults(results = []) {
  if (!results.length) {
    apiResults.className = "scroll-panel empty-state";
    apiResults.textContent = "Chưa có kết quả API automation.";
    return;
  }

  apiResults.className = "scroll-panel";
  apiResults.innerHTML = results
    .map(
      (api) => `
        <div class="api-preview-item">
          <div><span class="pill ${methodClass(api.method)}">${api.method}</span><code>${api.endpoint}</code></div>
          <div class="muted-line">Passed ${api.passed}/${api.total}</div>
          ${(api.testcases || [])
            .map(
              (testcase) => `
                <div class="muted-line ${testcase.ok ? "status-pass" : "status-fail"}">
                  ${testcase.ok ? "PASS" : "FAIL"} · ${testcase.name}
                  ${testcase.status_actual ? `(status ${testcase.status_actual})` : ""}
                  ${testcase.error ? `- ${testcase.error}` : ""}
                </div>
              `,
            )
            .join("")}
        </div>
      `,
    )
    .join("");
}

function payloadFromForm() {
  return {
    backend_src: backendInput.value.trim(),
    frontend_src: frontendInput.value.trim(),
    output_file: outputInput.value.trim(),
    limit: limitInput.value.trim(),
    dry_run: dryRunInput.checked,
  };
}

async function refreshState() {
  const data = await apiFetch("/api/state");
  backendInput.value = data.backend_default;
  frontendInput.value = data.frontend_default;
  outputInput.value = data.output_default;
  const loadedCollection = data.uploaded_collection?.exists ? data.uploaded_collection : data.existing_output;
  renderSummary(loadedCollection?.summary, loadedCollection?.path);
  renderCollection(loadedCollection?.preview || []);
  renderApiSummary(null);
}

scanButton.addEventListener("click", async () => {
  setBusy(true);
  updateLog("Đang scan route...");
  try {
    const data = await apiFetch("/api/scan", {
      method: "POST",
      body: JSON.stringify(payloadFromForm()),
    });

    if (!data.ok) {
      updateLog(data.stderr || data.stdout || "Scan thất bại.");
      renderRoutes([]);
      return;
    }

    renderRoutes(data.routes || []);
    updateLog(`Scan thành công: tìm thấy ${data.count} route.`);
  } catch (error) {
    updateLog(`Scan lỗi: ${error.message}`);
  } finally {
    setBusy(false);
  }
});

document.querySelector("#generator-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  setBusy(true);
  updateLog("Đang generate collection...");
  try {
    const data = await apiFetch("/api/generate", {
      method: "POST",
      body: JSON.stringify(payloadFromForm()),
    });

    if (!data.ok) {
      updateLog(data.stderr || data.stdout || "Generate thất bại.");
      return;
    }

    const output = await apiFetch("/api/output");
    renderSummary(output.summary, data.output_file || output.path);
    renderCollection(output.preview || []);
    updateLog((data.stdout || "Generate thành công.").trim());
  } catch (error) {
    updateLog(`Generate lỗi: ${error.message}`);
  } finally {
    setBusy(false);
  }
});

loadCollectionButton.addEventListener("click", async () => {
  const file = collectionFileInput.files?.[0];
  if (!file) {
    updateLog("Hãy chọn file collection JSON trước.");
    return;
  }

  setBusy(true);
  updateLog(`Đang nạp collection: ${file.name}...`);

  try {
    const content = await file.text();
    const data = await apiFetch("/api/load_collection", {
      method: "POST",
      body: JSON.stringify({ filename: file.name, content }),
    });

    if (!data.ok) {
      updateLog(data.message || "Không nạp được collection.");
      return;
    }

    renderSummary(data.summary, data.path);
    renderCollection(data.preview || []);
    renderApiSummary(null);
    renderApiResults([]);
    uiResults.textContent = "Chưa chạy UI automation.";
    updateLog(`Đã load collection ${data.filename}.`);
  } catch (error) {
    updateLog(`Load collection lỗi: ${error.message}`);
  } finally {
    setBusy(false);
  }
});

runApiButton.addEventListener("click", async () => {
  setBusy(true);
  updateLog("Đang chạy API automation...");
  try {
    const data = await apiFetch("/api/run_api_tests", {
      method: "POST",
      body: JSON.stringify({ base_url: apiBaseUrlInput.value.trim() }),
    });

    if (!data.ok) {
      updateLog(data.message || data.stderr || "API automation thất bại.");
      return;
    }

    renderApiSummary(data.summary);
    renderApiResults(data.results || []);
    updateLog(`API automation xong: ${data.summary.passed}/${data.summary.total} passed.`);
  } catch (error) {
    updateLog(`API automation lỗi: ${error.message}`);
  } finally {
    setBusy(false);
  }
});

runUiButton.addEventListener("click", async () => {
  setBusy(true);
  updateLog("Đang chạy Playwright smoke...");
  uiResults.textContent = "Đang chạy Playwright...";
  try {
    const data = await apiFetch("/api/run_ui_smoke", {
      method: "POST",
      body: JSON.stringify({}),
    });

    uiResults.textContent = [data.stdout, data.stderr].filter(Boolean).join("\n").trim() || "Không có output.";
    updateLog(data.ok ? "Playwright smoke chạy thành công." : (data.message || "Playwright smoke thất bại."));
  } catch (error) {
    uiResults.textContent = `Lỗi: ${error.message}`;
    updateLog(`UI automation lỗi: ${error.message}`);
  } finally {
    setBusy(false);
  }
});

refreshState().catch((error) => {
  updateLog(`Không tải được trạng thái ban đầu: ${error.message}`);
});
