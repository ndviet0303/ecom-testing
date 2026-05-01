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
const languageSelect = document.querySelector("#language-select");
const themeToggleButton = document.querySelector("#theme-toggle-button");
const screenToggleButton = document.querySelector("#screen-toggle-button");
const outputPath = document.querySelector("#output-path");
const copyPathButton = document.querySelector("#copy-path-button");
const downloadCollectionButton = document.querySelector("#download-collection-button");
const routesFilterInput = document.querySelector("#routes-filter");
const collectionFilterInput = document.querySelector("#collection-filter");
const statusDot = document.querySelector("#status-dot");
const statusTitle = document.querySelector("#status-title");
const statusDetail = document.querySelector("#status-detail");
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
const sidebarTabs = Array.from(document.querySelectorAll("[data-sidebar-tab]"));
const sidebarSections = Array.from(document.querySelectorAll("[data-sidebar-section]"));
const screenScopedNodes = Array.from(document.querySelectorAll("[data-screen]"));

const allActionButtons = [scanButton, generateButton, loadCollectionButton, runApiButton, runUiButton];
let cachedRoutes = [];
let cachedApis = [];
let currentLanguage = localStorage.getItem("automation-ui-lang") || "vi";
let currentTheme = localStorage.getItem("automation-ui-theme") || "light";
let currentScreenMode = localStorage.getItem("automation-ui-screen-mode") || "normal";
let currentSidebarTab = localStorage.getItem("automation-ui-sidebar-tab") || "generate";
let currentOutputPath = "";

const MESSAGES = {
  vi: {
    heroEyebrow: "Automation Suite",
    heroTitle: "Automation Tester",
    heroDesc: "Công cụ tự động hóa chuyên nghiệp cho web app hiện đại. Generate collection, scan route và chạy test API & UI với độ chính xác cao.",
    languageLabel: "Ngôn ngữ",
    generateTitle: "Generate Collection",
    generateDesc: "Trích xuất và xây dựng bộ testcase từ mã nguồn.",
    backendSource: "Backend path",
    frontendSource: "Frontend path",
    outputFile: "Output file",
    limitRoute: "Giới hạn route",
    dryRun: "Chạy thử (Dry Run)",
    scanRoutes: "Scan Routes",
    loaderTitle: "Collection Loader",
    loaderDesc: "Chọn file collection JSON để bắt đầu kiểm thử.",
    chooseCollection: "File Collection Testcase",
    loadCollection: "Load Collection",
    projectLabel: "Dự án",
    apiCountLabel: "APIs",
    generatedAtLabel: "Ngày tạo",
    collectionPath: "Đường dẫn file",
    copyPath: "Copy",
    downloadCollection: "Tải JSON",
    apiAutomation: "API Automation",
    apiAutomationDesc: "Chạy test API tự động với phản hồi thời gian thực.",
    apiBaseUrl: "API Base URL mục tiêu",
    runApiTests: "Chạy API Suite",
    passedLabel: "Thành công",
    failedLabel: "Thất bại",
    uiAutomation: "UI Smoke Test",
    uiAutomationDesc: "Tự động hóa trình duyệt bằng Playwright.",
    runUiSmoke: "Chạy Playwright Suite",
    playwrightOutput: "Log tự động hóa",
    statusReady: "Hệ thống sẵn sàng",
    statusReadyDetail: "Khởi tạo bằng cách scan route hoặc load collection.",
    scanning: "Đang scan route...",
    generating: "Đang generate...",
    runningApi: "Đang chạy API suite...",
    runningUi: "Đang chạy Playwright...",
    themeToLight: "Sáng",
    themeToDark: "Tối",
    screenToFocus: "Focus",
    screenToNormal: "Normal",
  },
  en: {
    heroEyebrow: "Automation Suite",
    heroTitle: "Automation Tester",
    heroDesc: "Professional-grade automation engine for modern web apps. Generate collections, scan routes, and execute full-suite API & UI tests with high precision.",
    languageLabel: "Lang",
    generateTitle: "Generate Collection",
    generateDesc: "Extract and build testcase collections from source code.",
    backendSource: "Backend path",
    frontendSource: "Frontend path",
    outputFile: "Output JSON file",
    limitRoute: "Route Limit",
    dryRun: "Dry Run",
    scanRoutes: "Scan Routes",
    loaderTitle: "Collection Loader",
    loaderDesc: "Select an existing JSON collection to begin testing.",
    chooseCollection: "Testcase Collection File",
    loadCollection: "Load Collection",
    projectLabel: "Project",
    apiCountLabel: "APIs",
    generatedAtLabel: "Created",
    collectionPath: "File Path",
    copyPath: "Copy",
    downloadCollection: "Download JSON",
    apiAutomation: "API Automation",
    apiAutomationDesc: "Run automated API tests with real-time feedback.",
    apiBaseUrl: "Target API Base URL",
    runApiTests: "Execute API Suite",
    passedLabel: "Passed",
    failedLabel: "Failed",
    uiAutomation: "UI Smoke Test",
    uiAutomationDesc: "Headless browser automation using Playwright.",
    runUiSmoke: "Run Playwright Suite",
    playwrightOutput: "Automation Logs",
    statusReady: "System Ready",
    statusReadyDetail: "Initialize by scanning routes or loading a collection.",
    scanning: "Scanning routes...",
    generating: "Generating collection...",
    runningApi: "Running API suite...",
    runningUi: "Running Playwright...",
    themeToLight: "Light",
    themeToDark: "Dark",
    screenToFocus: "Focus",
    screenToNormal: "Normal",
  },
};

function t(key) {
  return MESSAGES[currentLanguage]?.[key] || key;
}

function applyTranslations() {
  document.documentElement.lang = currentLanguage;
  document.querySelectorAll("[data-i18n]").forEach((node) => {
    const key = node.dataset.i18n;
    if (key) node.textContent = t(key);
  });
  
  document.title = `Automation Tester | ${currentTheme === 'dark' ? 'Pro Max' : 'Light'}`;
  
  scanButton.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> ${t("scanRoutes")}`;
  loadCollectionButton.textContent = t("loadCollection");
  runApiButton.textContent = t("runApiTests");
  runUiButton.textContent = t("runUiSmoke");
  copyPathButton.textContent = t("copyPath");
  if (downloadCollectionButton) {
    downloadCollectionButton.textContent = t("downloadCollection");
  }
  if (screenToggleButton) {
    screenToggleButton.textContent =
      currentScreenMode === "focus" ? t("screenToNormal") : t("screenToFocus");
  }
}

function applyTheme() {
  document.documentElement.setAttribute("data-theme", currentTheme);
  const nextThemeLabel = currentTheme === "dark" ? t("themeToLight") : t("themeToDark");
  themeToggleButton.innerHTML = currentTheme === "dark" 
    ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path></svg> ${nextThemeLabel}`
    : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path></svg> ${nextThemeLabel}`;
}

function applyScreenMode() {
  document.body.classList.toggle("screen-focus", currentScreenMode === "focus");
  if (screenToggleButton) {
    screenToggleButton.textContent =
      currentScreenMode === "focus" ? t("screenToNormal") : t("screenToFocus");
  }
}

function applySidebarTab() {
  sidebarTabs.forEach((tab) => {
    const isActive = tab.dataset.sidebarTab === currentSidebarTab;
    tab.classList.toggle("active", isActive);
  });

  screenScopedNodes.forEach((node) => {
    const scope = node.dataset.screen || "all";
    if (scope === "all") {
      node.classList.remove("screen-hidden");
      return;
    }
    const targets = scope.split(",").map((item) => item.trim());
    const visible = targets.includes(currentSidebarTab);
    node.classList.toggle("screen-hidden", !visible);
  });
}

async function apiFetch(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: { "Content-Type": "application/json", ...(options.headers || {}) },
  });
  const rawText = await response.text();
  const contentType = response.headers.get("content-type") || "";

  if (!contentType.includes("application/json")) {
    throw new Error(`Endpoint ${url} error: No JSON response.`);
  }

  const data = JSON.parse(rawText);
  if (!response.ok) throw new Error(data.message || `Error ${response.status}`);
  return data;
}

function setBusy(isBusy, btn = null, loadingKey = null) {
  allActionButtons.forEach(b => b.disabled = isBusy);
  if (!btn) return;

  if (isBusy && loadingKey) {
    btn.dataset.prevText = btn.innerHTML;
    btn.textContent = t(loadingKey);
    return;
  }

  if (!isBusy && btn.dataset.prevText) {
    btn.innerHTML = btn.dataset.prevText;
    delete btn.dataset.prevText;
  }
}

function setStatus(type, titleKey, detail) {
  statusDot.className = "status-dot";
  if (type === "running") {
    statusDot.classList.add("active", "pulse");
    statusDot.style.background = "var(--accent-secondary)";
  } else if (type === "success") {
    statusDot.classList.add("active");
    statusDot.style.background = "var(--accent-success)";
    statusDot.style.boxShadow = "0 0 12px var(--accent-success)";
  } else if (type === "error") {
    statusDot.classList.add("active");
    statusDot.style.background = "var(--accent-danger)";
    statusDot.style.boxShadow = "0 0 12px var(--accent-danger)";
    shakeUI();
  } else {
    statusDot.style.background = "var(--text-muted)";
    statusDot.style.boxShadow = "none";
  }

  statusTitle.textContent = t(titleKey) || titleKey;
  statusDetail.textContent = detail || "";
}

function shakeUI() {
  document.body.animate([
    { transform: 'translateX(0)' },
    { transform: 'translateX(-5px)' },
    { transform: 'translateX(5px)' },
    { transform: 'translateX(0)' }
  ], { duration: 300, iterations: 2 });
}

function methodClass(method) {
  return String(method || "").toLowerCase();
}

function renderRoutes(routes = []) {
  cachedRoutes = routes;
  const query = routesFilterInput.value.trim().toLowerCase();
  const filtered = routes.filter(r => `${r.method} ${r.path}`.toLowerCase().includes(query));

  if (!filtered.length) {
    routesPreview.innerHTML = `<div style="color:var(--text-muted);text-align:center;padding-top:40px;">No routes found</div>`;
    return;
  }

  routesPreview.innerHTML = filtered.map(r => `
    <div style="padding: 12px 0; border-bottom: 1px solid var(--panel-border);">
      <div style="display:flex; align-items:center; gap:12px;">
        <span class="pill ${methodClass(r.method)}">${r.method}</span>
        <code style="word-break:break-all;">${r.path}</code>
      </div>
      <div style="color:var(--text-muted); font-size:0.8rem; margin-top:4px;">${r.controller || "Closure"}@${r.action || "-"}</div>
    </div>
  `).join("");
}

function renderCollection(apis = []) {
  cachedApis = apis;
  const query = collectionFilterInput.value.trim().toLowerCase();
  const filtered = apis.filter(a => `${a.method} ${a.endpoint}`.toLowerCase().includes(query));

  if (!filtered.length) {
    collectionPreview.innerHTML = `<div style="color:var(--text-muted);text-align:center;padding-top:40px;">Collection empty or filtered</div>`;
    return;
  }

  collectionPreview.innerHTML = filtered.map(a => `
    <div style="padding: 12px 0; border-bottom: 1px solid var(--panel-border);">
      <div style="display:flex; align-items:center; gap:12px;">
        <span class="pill ${methodClass(a.method)}">${a.method}</span>
        <code style="word-break:break-all;">${a.endpoint}</code>
      </div>
      <div style="color:var(--text-muted); font-size:0.8rem; margin-top:4px;">Testcases: ${a.testcases?.length || 0} | Context: ${a.retrieved_context?.length || 0}</div>
    </div>
  `).join("");
}

function renderSummary(summary, path) {
  summaryProject.textContent = summary?.project || "-";
  summaryCount.textContent = summary?.api_count ?? "-";
  summaryTime.textContent = summary?.generated_at?.split('T')[0] || "-";
  currentOutputPath = path || "";
  outputPath.textContent = currentOutputPath || "-";
  copyPathButton.disabled = !currentOutputPath;
  if (downloadCollectionButton) {
    downloadCollectionButton.disabled = !currentOutputPath;
  }
}

function renderApiResults(results = [], summary = null) {
  apiTotal.textContent = summary?.total ?? "-";
  apiPassed.textContent = summary?.passed ?? "-";
  apiFailed.textContent = summary?.failed ?? "-";

  if (!results.length) {
    apiResults.innerHTML = `<div style="color:var(--text-muted);text-align:center;padding-top:60px;">Ready for execution</div>`;
    return;
  }

  apiResults.innerHTML = results.map(a => `
    <div style="margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--panel-border);">
      <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
        <span class="pill ${methodClass(a.method)}">${a.method}</span>
        <code>${a.endpoint}</code>
      </div>
      <div style="display:flex; flex-direction:column; gap:4px;">
        ${(a.testcases || []).map(t => `
          <div style="font-size:0.85rem; color:${t.ok ? 'var(--accent-success)' : 'var(--accent-danger)'}; display:flex; justify-content:space-between;">
            <span>${t.ok ? '✓' : '✗'} ${t.name}</span>
            <span style="opacity:0.7;">${t.status_actual || ''}</span>
          </div>
        `).join("")}
      </div>
    </div>
  `).join("");
}

async function refreshState() {
  const data = await apiFetch("/api/state");
  backendInput.value = data.backend_default || "";
  frontendInput.value = data.frontend_default || "";
  outputInput.value = data.output_default || "";
  const loaded = data.uploaded_collection?.exists ? data.uploaded_collection : data.existing_output;
  renderSummary(loaded?.summary, loaded?.path);
  renderCollection(loaded?.preview || []);
  renderApiResults([], null);
  setStatus("idle", "statusReady", "Persistent state restored.");
}

routesFilterInput.addEventListener("input", () => renderRoutes(cachedRoutes));
collectionFilterInput.addEventListener("input", () => renderCollection(cachedApis));

languageSelect.addEventListener("change", () => {
  currentLanguage = languageSelect.value;
  localStorage.setItem("automation-ui-lang", currentLanguage);
  applyTranslations();
  renderRoutes(cachedRoutes);
  renderCollection(cachedApis);
});

themeToggleButton.addEventListener("click", () => {
  currentTheme = currentTheme === "dark" ? "light" : "dark";
  localStorage.setItem("automation-ui-theme", currentTheme);
  applyTheme();
  applyTranslations();
});

if (screenToggleButton) {
  screenToggleButton.addEventListener("click", () => {
    currentScreenMode = currentScreenMode === "focus" ? "normal" : "focus";
    localStorage.setItem("automation-ui-screen-mode", currentScreenMode);
    applyScreenMode();
  });
}

sidebarTabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    currentSidebarTab = tab.dataset.sidebarTab || "generate";
    localStorage.setItem("automation-ui-sidebar-tab", currentSidebarTab);
    applySidebarTab();
  });
});

copyPathButton.addEventListener("click", async () => {
  const path = outputPath.textContent.trim();
  if (!path || path === "-") return;
  try {
    await navigator.clipboard.writeText(path);
    const prevText = copyPathButton.textContent;
    copyPathButton.textContent = "Copied!";
    setTimeout(() => copyPathButton.textContent = prevText, 2000);
  } catch (e) {
    setStatus("error", "Copy Failed", e.message);
  }
});

if (downloadCollectionButton) {
  downloadCollectionButton.addEventListener("click", async () => {
    if (!currentOutputPath) return;
    try {
      const res = await fetch(`/api/download_output?path=${encodeURIComponent(currentOutputPath)}`);
      if (!res.ok) {
        const text = await res.text();
        throw new Error(text || `HTTP ${res.status}`);
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      const fallbackName = currentOutputPath.split("/").pop() || "generated-testcases.json";
      link.download = fallbackName;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
      setStatus("success", "Collection Ready", `Downloaded ${fallbackName}`);
    } catch (e) {
      setStatus("error", "Download Error", e.message);
    }
  });
}

scanButton.addEventListener("click", async () => {
  setBusy(true, scanButton, "scanning");
  setStatus("running", "scanning", "Reading backend architecture...");
  try {
    const data = await apiFetch("/api/scan", {
      method: "POST",
      body: JSON.stringify({ backend_src: backendInput.value.trim() }),
    });
    renderRoutes(data.routes || []);
    setStatus("success", "Scan Complete", `Found ${data.count} routes.`);
  } catch (e) {
    setStatus("error", "Scan Error", e.message);
  } finally {
    setBusy(false, scanButton);
  }
});

document.querySelector("#generator-form").addEventListener("submit", async (e) => {
  e.preventDefault();
  setBusy(true, generateButton, "generating");
  setStatus("running", "generating", "Building test vectors...");
  try {
    const payload = {
      backend_src: backendInput.value.trim(),
      frontend_src: frontendInput.value.trim(),
      output_file: outputInput.value.trim(),
      limit: limitInput.value.trim(),
      dry_run: dryRunInput.checked,
      provider: "groq",
      model: "llama-3.1-8b-instant",
    };
    const data = await apiFetch("/api/generate", { method: "POST", body: JSON.stringify(payload) });
    const output = await apiFetch("/api/output");
    renderSummary(output.summary, data.output_file || output.path);
    renderCollection(output.preview || []);
    setStatus("success", "Generation Complete", data.output_file || "Collection ready.");
  } catch (e) {
    setStatus("error", "Generation Error", e.message);
  } finally {
    setBusy(false, generateButton);
  }
});

loadCollectionButton.addEventListener("click", async () => {
  const file = collectionFileInput.files?.[0];
  if (!file) return setStatus("error", "No File", "Please select a JSON collection.");
  
  setBusy(true, loadCollectionButton);
  setStatus("running", "Loading Collection", file.name);
  try {
    const content = await file.text();
    const data = await apiFetch("/api/load_collection", {
      method: "POST",
      body: JSON.stringify({ filename: file.name, content }),
    });
    renderSummary(data.summary, data.path);
    renderCollection(data.preview || []);
    setStatus("success", "Collection Loaded", `${data.summary?.api_count || 0} APIs ready.`);
  } catch (e) {
    setStatus("error", "Load Error", e.message);
  } finally {
    setBusy(false, loadCollectionButton);
  }
});

runApiButton.addEventListener("click", async () => {
  setBusy(true, runApiButton, "runningApi");
  setStatus("running", "runningApi", "Executing test cases...");
  try {
    const data = await apiFetch("/api/run_api_tests", {
      method: "POST",
      body: JSON.stringify({ base_url: apiBaseUrlInput.value.trim() }),
    });
    renderApiResults(data.results || [], data.summary);
    setStatus(data.summary?.failed > 0 ? "error" : "success", "API Suite Complete", `${data.summary?.passed}/${data.summary?.total} passed.`);
  } catch (e) {
    setStatus("error", "Execution Error", e.message);
  } finally {
    setBusy(false, runApiButton);
  }
});

runUiButton.addEventListener("click", async () => {
  setBusy(true, runUiButton, "runningUi");
  setStatus("running", "runningUi", "Launching Playwright...");
  uiResults.textContent = ">>> PLAYWRIGHT EXECUTION STARTED...";
  try {
    const data = await apiFetch("/api/run_ui_smoke", { method: "POST", body: JSON.stringify({}) });
    uiResults.textContent = [data.stdout, data.stderr].filter(Boolean).join("\n").trim();
    setStatus(data.ok ? "success" : "error", data.ok ? "UI Tests Passed" : "UI Tests Failed", "See output for details.");
  } catch (e) {
    uiResults.textContent = `>>> ERROR: ${e.message}`;
    setStatus("error", "UI Automation Error", e.message);
  } finally {
    setBusy(false, runUiButton);
  }
});

applyTheme();
applyTranslations();
applyScreenMode();
applySidebarTab();
refreshState().catch(console.error);
languageSelect.value = currentLanguage;
