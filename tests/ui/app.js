const backendInput = document.querySelector("#backend-src");
const frontendInput = document.querySelector("#frontend-src");
const chooseBackendButton = document.querySelector("#choose-backend-button");
const chooseFrontendButton = document.querySelector("#choose-frontend-button");
const aiProviderSelect = document.querySelector("#ai-provider");
const aiModelInput = document.querySelector("#ai-model");
const aiApiKeyInput = document.querySelector("#ai-api-key");
const toggleApiKeyButton = document.querySelector("#toggle-api-key-button");
const aiKeyStatus = document.querySelector("#ai-key-status");
const saveAiSettingsButton = document.querySelector("#save-ai-settings-button");
const limitInput = document.querySelector("#limit");
const dryRunInput = document.querySelector("#dry-run");
const collectionFileInput = document.querySelector("#collection-file");
const runCollectionFileInput = document.querySelector("#run-collection-file");
const loadCollectionButton = document.querySelector("#load-collection-button");
const apiBaseUrlInput = document.querySelector("#api-base-url");
const siteBaseUrlInput = document.querySelector("#site-base-url");
const runApiButton = document.querySelector("#run-api-button");
const runUiButton = document.querySelector("#run-ui-button");
const showPlaywrightUiInput = document.querySelector("#show-playwright-ui");
const scanButton = document.querySelector("#scan-button");
const generateButton = document.querySelector("#generate-button");
const languageSelect = document.querySelector("#language-select");
const themeToggleButton = document.querySelector("#theme-toggle-button");
const screenToggleButton = document.querySelector("#screen-toggle-button");
const outputPath = document.querySelector("#output-path");
const copyPathButton = document.querySelector("#copy-path-button");
const downloadCollectionButton = document.querySelector("#download-collection-button");
const downloadGeneratedButton = document.querySelector("#download-generated-button");
const downloadResultButton = document.querySelector("#download-result-button");
const toggleRawResultButton = document.querySelector("#toggle-raw-result-button");
const analysisApiResultFile = document.querySelector("#analysis-api-result-file");
const analysisPlaywrightResultFile = document.querySelector("#analysis-playwright-result-file");
const analyzeResultButton = document.querySelector("#analyze-result-button");
const downloadAnalysisButton = document.querySelector("#download-analysis-button");
const downloadPlaywrightReportButton = document.querySelector("#download-playwright-report-button");
const routesFilterInput = document.querySelector("#routes-filter");
const collectionFilterInput = document.querySelector("#collection-filter");
const statusDot = document.querySelector("#status-dot");
const statusTitle = document.querySelector("#status-title");
const statusDetail = document.querySelector("#status-detail");
const routesPreview = document.querySelector("#routes-preview");
const collectionPreview = document.querySelector("#collection-preview");
const apiResults = document.querySelector("#api-results");
const apiResultMeta = document.querySelector("#api-result-meta");
const apiResultRaw = document.querySelector("#api-result-raw");
const analysisMeta = document.querySelector("#analysis-meta");
const analysisResults = document.querySelector("#analysis-results");
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

// Terminal console elements
const terminalSection = document.querySelector("#terminal-section");
const clearTerminalBtn = document.querySelector("#clear-terminal-btn");
const toggleTerminalBtn = document.querySelector("#toggle-terminal-btn");
const terminalConsole = document.querySelector("#terminal-console");

const allActionButtons = [scanButton, generateButton, saveAiSettingsButton, loadCollectionButton, runApiButton, runUiButton, analyzeResultButton];
let cachedRoutes = [];
let cachedApis = [];
let currentLanguage = localStorage.getItem("automation-ui-lang") || "vi";
let currentTheme = localStorage.getItem("automation-ui-theme") || "light";
let currentScreenMode = localStorage.getItem("automation-ui-screen-mode") || "normal";
let currentSidebarTab = localStorage.getItem("automation-ui-sidebar-tab") || "generate";
let currentOutputPath = "";
let currentResultPath = "";
let currentAnalysisPath = "";
let currentPlaywrightReportPath = "";
let currentRawResult = null;
let currentAiHasKey = false;

const MESSAGES = {
  vi: {
    heroEyebrow: "Automation Suite",
    heroTitle: "Automation Tester",
    heroDesc: "Công cụ tự động hóa chuyên nghiệp cho web app hiện đại. Generate collection, scan route và chạy test API & UI với độ chính xác cao.",
    languageLabel: "Ngôn ngữ",
    settingsTab: "Settings",
    analysisTitle: "Groq Analysis",
    analysisDesc: "Upload API result và Playwright report để AI viết report tester tổng hợp.",
    analysisApiResult: "API suite result JSON",
    analysisPlaywrightResult: "Playwright suite report JSON",
    analyzeResults: "Phân tích bằng Groq",
    downloadAnalysis: "Tải JSON phân tích",
    downloadPlaywrightReport: "Tải JSON report Playwright",
    analysisResultTitle: "AI Analysis",
    generateTitle: "Generate Collection",
    generateDesc: "Trích xuất và xây dựng bộ testcase từ mã nguồn.",
    settingsTitle: "Settings",
    settingsDesc: "Cấu hình AI provider dùng khi Generate không bật Dry Run.",
    settingsGuideTitle: "Cấu hình AI",
    settingsGuideDesc: "Settings được lưu local vào ai/.env và không commit.",
    aiSettingsTitle: "AI Settings",
    aiProvider: "Provider",
    aiModel: "Model",
    aiApiKey: "API key",
    showSecret: "Hiện",
    hideSecret: "Ẩn",
    saveAiSettings: "Lưu AI Settings",
    aiConfigured: "Đã có key",
    aiMissingKey: "Thiếu key",
    backendSource: "Backend path",
    frontendSource: "Frontend path",
    browsePath: "Chọn",
    downloadGenerated: "Tải JSON vừa tạo",
    downloadResult: "Tải JSON kết quả",
    rawJson: "JSON thô",
    parsedView: "Giao diện parse",
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
    siteBaseUrl: "Site URL mục tiêu",
    runApiTests: "Chạy API Suite",
    passedLabel: "Thành công",
    failedLabel: "Thất bại",
    uiAutomation: "UI Smoke Test",
    uiAutomationDesc: "Tự động hóa trình duyệt bằng Playwright.",
    runUiSmoke: "Chạy Playwright Suite",
    showPlaywrightUi: "Xem UI Playwright",
    playwrightOutput: "Log tự động hóa",
    statusReady: "Hệ thống sẵn sàng",
    statusReadyDetail: "Khởi tạo bằng cách scan route hoặc load collection.",
    scanning: "Đang scan route...",
    generating: "Đang generate...",
    runningApi: "Đang chạy API suite...",
    runningUi: "Đang chạy Playwright...",
    analyzingResults: "Đang train context và phân tích...",
    themeToLight: "Sáng",
    themeToDark: "Tối",
    screenToFocus: "Focus",
    screenToNormal: "Normal",
    terminalTitle: "Terminal Console - Nhật ký chạy",
    clearTerminal: "Xóa log",
    minimizeTerminal: "Thu nhỏ",
    expandTerminal: "Mở rộng",
    slidesTab: "Slides",
    slidesPanelTitle: "Slide Thuyết Trình",
    slidesPanelDesc: "Slide thuyết trình tương tác phục vụ môn đánh giá & kiểm định chất lượng.",
  },
  en: {
    heroEyebrow: "Automation Suite",
    heroTitle: "Automation Tester",
    heroDesc: "Professional-grade automation engine for modern web apps. Generate collections, scan routes, and execute full-suite API & UI tests with high precision.",
    languageLabel: "Lang",
    settingsTab: "Settings",
    analysisTitle: "Groq Analysis",
    analysisDesc: "Upload API result and Playwright report so AI writes an integrated tester report.",
    analysisApiResult: "API suite result JSON",
    analysisPlaywrightResult: "Playwright suite report JSON",
    analyzeResults: "Analyze with Groq",
    downloadAnalysis: "Download analysis JSON",
    downloadPlaywrightReport: "Download Playwright report JSON",
    analysisResultTitle: "AI Analysis",
    generateTitle: "Generate Collection",
    generateDesc: "Extract and build testcase collections from source code.",
    settingsTitle: "Settings",
    settingsDesc: "Configure the AI provider used when Generate runs without Dry Run.",
    settingsGuideTitle: "AI Configuration",
    settingsGuideDesc: "Settings are saved locally to ai/.env and are not committed.",
    aiSettingsTitle: "AI Settings",
    aiProvider: "Provider",
    aiModel: "Model",
    aiApiKey: "API key",
    showSecret: "Show",
    hideSecret: "Hide",
    saveAiSettings: "Save AI Settings",
    aiConfigured: "Key configured",
    aiMissingKey: "Missing key",
    backendSource: "Backend path",
    frontendSource: "Frontend path",
    browsePath: "Choose",
    downloadGenerated: "Download generated JSON",
    downloadResult: "Download result JSON",
    rawJson: "Raw JSON",
    parsedView: "Parsed View",
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
    siteBaseUrl: "Target Site URL",
    runApiTests: "Execute API Suite",
    passedLabel: "Passed",
    failedLabel: "Failed",
    uiAutomation: "UI Smoke Test",
    uiAutomationDesc: "Headless browser automation using Playwright.",
    runUiSmoke: "Run Playwright Suite",
    showPlaywrightUi: "Show Playwright UI",
    playwrightOutput: "Automation Logs",
    statusReady: "System Ready",
    statusReadyDetail: "Initialize by scanning routes or loading a collection.",
    scanning: "Scanning routes...",
    generating: "Generating collection...",
    runningApi: "Running API suite...",
    runningUi: "Running Playwright...",
    analyzingResults: "Training context and analyzing...",
    themeToLight: "Light",
    themeToDark: "Dark",
    screenToFocus: "Focus",
    screenToNormal: "Normal",
    terminalTitle: "Terminal Console - Live Logs",
    clearTerminal: "Clear",
    minimizeTerminal: "Minimize",
    expandTerminal: "Expand",
    slidesTab: "Slides",
    slidesPanelTitle: "Presentation Slides",
    slidesPanelDesc: "Interactive slide presentation for the software testing course.",
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
  if (downloadGeneratedButton) {
    downloadGeneratedButton.textContent = t("downloadGenerated");
  }
  if (downloadResultButton) {
    downloadResultButton.textContent = t("downloadResult");
  }
  if (analyzeResultButton) {
    analyzeResultButton.textContent = t("analyzeResults");
  }
  if (downloadAnalysisButton) {
    downloadAnalysisButton.textContent = t("downloadAnalysis");
  }
  if (downloadPlaywrightReportButton) {
    downloadPlaywrightReportButton.textContent = t("downloadPlaywrightReport");
  }
  if (toggleRawResultButton && !currentRawResult) {
    toggleRawResultButton.textContent = t("rawJson");
  }
  if (saveAiSettingsButton) {
    saveAiSettingsButton.textContent = t("saveAiSettings");
  }
  if (toggleApiKeyButton) {
    toggleApiKeyButton.textContent = aiApiKeyInput.type === "password" ? t("showSecret") : t("hideSecret");
  }
  if (screenToggleButton) {
    screenToggleButton.textContent =
      currentScreenMode === "focus" ? t("screenToNormal") : t("screenToFocus");
  }
  if (toggleTerminalBtn) {
    const isCollapsed = terminalSection.classList.contains("collapsed");
    toggleTerminalBtn.textContent = isCollapsed ? t("expandTerminal") : t("minimizeTerminal");
  }
}

function applyAiSettings(settings = {}) {
  aiProviderSelect.value = settings.provider || "groq";
  aiModelInput.value = settings.model || "llama-3.1-8b-instant";
  currentAiHasKey = Boolean(settings.has_api_key);
  aiApiKeyInput.value = "";
  aiApiKeyInput.placeholder = settings.has_api_key
    ? `${settings.key_name || "API_KEY"} already saved`
    : `Paste ${settings.key_name || "API key"}, then save`;
  aiKeyStatus.textContent = settings.has_api_key ? t("aiConfigured") : t("aiMissingKey");
  aiKeyStatus.classList.toggle("is-ok", Boolean(settings.has_api_key));
}

async function selectPath({ kind, title, input }) {
  try {
    setStatus("running", "Selecting Path", title);
    const data = await apiFetch("/api/select_path", {
      method: "POST",
      body: JSON.stringify({
        kind,
        title,
        initial: input.value.trim(),
      }),
    });
    if (data.ok && data.path) {
      input.value = data.path;
      setStatus("success", "Path Selected", data.path);
      return;
    }
    if (!data.cancelled) {
      setStatus("error", "Path Error", data.message || "Cannot select path.");
    } else {
      setStatus("idle", "statusReady", t("statusReadyDetail"));
    }
  } catch (e) {
    setStatus("error", "Path Error", e.message);
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

async function streamFetch(url, options = {}, onLog = null, onResult = null) {
  const response = await fetch(url, {
    ...options,
    headers: { "Content-Type": "application/json", ...(options.headers || {}) },
  });

  if (!response.ok) {
    let errorMsg = `Error ${response.status}`;
    try {
      const err = JSON.parse(await response.text());
      errorMsg = err.message || errorMsg;
    } catch (e) {}
    throw new Error(errorMsg);
  }

  const reader = response.body.getReader();
  const decoder = new TextDecoder("utf-8");
  let buffer = "";

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;

    buffer += decoder.decode(value, { stream: true });
    const lines = buffer.split("\n");
    buffer = lines.pop(); // Keep remaining incomplete line in buffer

    for (const line of lines) {
      if (line.trim()) {
        try {
          const packet = JSON.parse(line);
          if (packet.type === "log" && onLog) {
            onLog(packet.content);
          } else if (packet.type === "result" && onResult) {
            onResult(packet);
          }
        } catch (e) {
          console.error("Failed to parse NDJSON line:", line, e);
        }
      }
    }
  }

  if (buffer.trim()) {
    try {
      const packet = JSON.parse(buffer);
      if (packet.type === "log" && onLog) {
        onLog(packet.content);
      } else if (packet.type === "result" && onResult) {
        onResult(packet);
      }
    } catch (e) {
      console.error("Failed to parse final NDJSON line:", buffer, e);
    }
  }
}

function appendLog(content) {
  if (content === undefined || content === null) return;
  const escaped = escapeHtml(content);
  let styled = escaped;

  // Simple coloring rules for premium UX
  if (content.startsWith("Command:") || content.startsWith("Executing:") || content.startsWith("Executing command:")) {
    styled = `<span class="term-cmd">${escaped}</span>`;
  } else if (content.includes("Failed") || content.includes("Error") || content.includes("FAIL")) {
    styled = `<span class="term-error">${escaped}</span>`;
  } else if (content.includes("Passed") || content.includes("Successfully") || content.includes("Success") || content.includes("OK")) {
    styled = `<span class="term-success">${escaped}</span>`;
  } else if (content.includes("Warning") || content.includes("Warn") || content.startsWith("  ->")) {
    styled = `<span class="term-warn">${escaped}</span>`;
  } else {
    styled = `<span class="term-info">${escaped}</span>`;
  }

  terminalConsole.innerHTML += styled + "\n";
  // Auto-scroll to bottom
  terminalConsole.scrollTop = terminalConsole.scrollHeight;
}

function clearTerminal() {
  terminalConsole.innerHTML = "";
  appendLog("Ready to stream logs...");
}

function expandTerminal() {
  if (terminalSection) {
    terminalSection.classList.remove("collapsed");
    updateTerminalToggleLabel();
  }
}

function toggleTerminal() {
  if (terminalSection) {
    const isCollapsed = terminalSection.classList.toggle("collapsed");
    updateTerminalToggleLabel();
  }
}

function updateTerminalToggleLabel() {
  if (toggleTerminalBtn) {
    const isCollapsed = terminalSection.classList.contains("collapsed");
    toggleTerminalBtn.textContent = isCollapsed ? t("expandTerminal") : t("minimizeTerminal");
  }
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

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function formatDuration(ms) {
  if (ms == null) return "-";
  return ms >= 1000 ? `${(ms / 1000).toFixed(2)}s` : `${ms}ms`;
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
  if (downloadGeneratedButton) {
    downloadGeneratedButton.disabled = !currentOutputPath;
  }
}

function renderApiResults(results = [], summary = null) {
  apiTotal.textContent = summary?.total ?? "-";
  apiPassed.textContent = summary?.passed ?? "-";
  apiFailed.textContent = summary?.failed ?? "-";
  apiResultMeta.textContent = summary
    ? `${summary.base_url || "-"} | ${formatDuration(summary.duration_ms)}`
    : "No execution yet.";

  if (!results.length) {
    apiResults.innerHTML = `<div style="color:var(--text-muted);text-align:center;padding-top:60px;">Ready for execution</div>`;
    return;
  }

  apiResults.innerHTML = results.map(a => `
    <div class="result-group">
      <div class="result-group-head">
        <div class="result-route">
          <span class="pill ${methodClass(a.method)}">${escapeHtml(a.method)}</span>
          <code>${escapeHtml(a.endpoint)}</code>
        </div>
        <span class="${a.failed ? "result-count failed" : "result-count"}">${a.passed}/${a.total}</span>
      </div>
      <div class="testcase-list">
        ${(a.testcases || []).map(t => `
          <div class="testcase-row ${t.ok ? "passed" : "failed"}">
            <div class="testcase-main">
              <span class="testcase-status">${t.ok ? "OK" : "FAIL"}</span>
              <span class="testcase-name">${escapeHtml(t.name || "Unnamed testcase")}</span>
            </div>
            <div class="testcase-status-pair">
              <span>expected ${escapeHtml(t.status_expected ?? "-")}</span>
              <span>actual ${escapeHtml(t.status_actual ?? "-")}</span>
            </div>
            ${t.ok ? "" : `
              <div class="testcase-detail">
                <div><strong>URL</strong><code>${escapeHtml(t.url)}</code></div>
                ${t.error ? `<div><strong>Error</strong><span>${escapeHtml(t.error)}</span></div>` : ""}
                ${(t.missing_body_contains || []).length ? `<div><strong>Missing body_contains</strong><span>${escapeHtml(t.missing_body_contains.join(", "))}</span></div>` : ""}
                ${t.response_body ? `<details><summary>Response body</summary><pre>${escapeHtml(t.response_body)}</pre></details>` : ""}
              </div>
            `}
          </div>
        `).join("")}
      </div>
    </div>
  `).join("");
}

function renderAnalysis(payload = null) {
  const analysis = payload?.analysis;
  currentAnalysisPath = payload?.analysis_file || "";
  downloadAnalysisButton.disabled = !currentAnalysisPath;
  const tokenMeta = payload?.training_stats
    ? ` | input ~${payload.training_stats.estimated_input_tokens}/${payload.training_stats.max_input_tokens} tokens`
    : "";
  const remainingMeta = payload?.rate_limit?.remaining_tokens
    ? ` | remaining ${payload.rate_limit.remaining_tokens} TPM`
    : "";
  analysisMeta.textContent = payload?.model ? `Groq | ${payload.model}${tokenMeta}${remainingMeta}` : "No analysis yet.";

  if (!analysis) {
    analysisResults.innerHTML = `<div style="color:var(--text-muted);text-align:center;padding-top:60px;">Waiting for Groq analysis...</div>`;
    return;
  }

  const executiveSummary = analysis.executive_summary || analysis.summary || "-";
  const overallStatus = normalizeReportStatus(analysis.overall_status, analysis.risk_level);
  const suiteSummary = analysis.suite_summary || null;
  const correlation = Array.isArray(analysis.api_ui_correlation) ? analysis.api_ui_correlation : [];
  const improvementAreas = Array.isArray(analysis.improvement_areas) ? analysis.improvement_areas : [];
  const defectReport = Array.isArray(analysis.defect_report) ? analysis.defect_report : [];
  const testQualityGaps = Array.isArray(analysis.test_quality_gaps) ? analysis.test_quality_gaps : [];
  const rootCauses = Array.isArray(analysis.root_causes) ? analysis.root_causes : [];
  const actions = Array.isArray(analysis.recommended_actions) ? analysis.recommended_actions : [];
  const regressionNotes = Array.isArray(analysis.regression_notes) ? analysis.regression_notes : [];
  const trainedNotes = Array.isArray(analysis.trained_context_notes) ? analysis.trained_context_notes : [];
  const hasIssues = defectReport.length || rootCauses.length || actions.length || improvementAreas.length || testQualityGaps.length;

  analysisResults.innerHTML = `
    <div class="qa-report-banner qa-status-${escapeHtml(overallStatus.toLowerCase())}">
      <div>
        <span class="summary-label">Kết luận</span>
        <strong>${escapeHtml(overallStatus)}</strong>
      </div>
      <p>${escapeHtml(executiveSummary)}</p>
    </div>

    <div class="analysis-summary">
      ${renderMetricCard("Pass rate", `${analysis.pass_rate ?? "-"}%`)}
      ${renderMetricCard("Risk", analysis.risk_level || "-")}
      ${suiteSummary ? renderMetricCard("API suite", suiteSummary.api || "-") : ""}
      ${suiteSummary ? renderMetricCard("Playwright suite", suiteSummary.ui || "-") : ""}
    </div>

    <section class="analysis-section">
      <h3>Nhận định tester</h3>
      <p>${escapeHtml(executiveSummary)}</p>
      ${suiteSummary?.coverage_note ? `<p class="text-muted">${escapeHtml(suiteSummary.coverage_note)}</p>` : ""}
    </section>

    ${correlation.length ? `
      <section class="analysis-section">
        <h3>Liên hệ API/UI</h3>
        ${correlation.map(item => `
          <article class="analysis-item">
            <div class="analysis-item-head">
              <strong>${escapeHtml(item.title || "Liên hệ")}</strong>
              <span>${escapeHtml(item.confidence || "unknown")}</span>
            </div>
            <p>${escapeHtml(item.explanation || "")}</p>
            <ul>${(item.evidence || []).map(line => `<li>${escapeHtml(line)}</li>`).join("")}</ul>
          </article>
        `).join("")}
      </section>
    ` : ""}

    ${defectReport.length ? `
      <section class="analysis-section">
      <h3>Defect cần xử lý</h3>
      ${defectReport.length ? defectReport.map(item => `
        <article class="analysis-item">
          <div class="analysis-item-head">
            <strong>${escapeHtml(item.severity || "medium")} - ${escapeHtml(item.title || "Untitled")}</strong>
            <span>${escapeHtml(item.owner || "unknown")}</span>
          </div>
          <p>${escapeHtml(item.impact || "")}</p>
          <ul>${(item.evidence || []).map(line => `<li>${escapeHtml(line)}</li>`).join("")}</ul>
        </article>
      `).join("") : ""}
      </section>
    ` : ""}

    ${rootCauses.length ? `
      <section class="analysis-section">
      <h3>Root cause</h3>
      ${rootCauses.map(item => `
        <article class="analysis-item">
          <div class="analysis-item-head">
            <strong>${escapeHtml(item.title || "Untitled")}</strong>
            <span>${escapeHtml(item.likely_owner || "unknown")} | ${escapeHtml(item.confidence || "unknown")}</span>
          </div>
          <ul>${(item.evidence || []).map(line => `<li>${escapeHtml(line)}</li>`).join("")}</ul>
        </article>
      `).join("")}
      </section>
    ` : ""}

    ${improvementAreas.length ? `
      <section class="analysis-section">
      <h3>Cần cải thiện</h3>
      ${improvementAreas.map(item => `
        <article class="analysis-item">
          <div class="analysis-item-head">
            <strong>${escapeHtml(item.area || "Hạng mục")}</strong>
            <span>${escapeHtml(item.priority || "P2")}</span>
          </div>
          <p>${escapeHtml(item.recommendation || item.reason || "")}</p>
        </article>
      `).join("")}
      </section>
    ` : ""}

    ${actions.length ? `
      <section class="analysis-section">
      <h3>Action đề xuất</h3>
      ${actions.map(item => `
        <article class="analysis-item">
          <div class="analysis-item-head">
            <strong>${escapeHtml(item.priority || "P2")} - ${escapeHtml(item.action || "-")}</strong>
          </div>
          <p>${escapeHtml(item.why || "")}</p>
        </article>
      `).join("")}
      </section>
    ` : ""}

    ${testQualityGaps.length ? renderListSection("Khoảng trống chất lượng test", testQualityGaps) : ""}
    ${regressionNotes.length ? renderListSection("Lưu ý regression", regressionNotes) : ""}
    ${!hasIssues ? `
      <section class="analysis-section qa-empty-state">
        <h3>Không phát hiện lỗi bắt buộc</h3>
        <p>API suite và Playwright suite chưa trả về defect/action cần xử lý. Nên duy trì regression run sau các thay đổi liên quan auth, cart, checkout và builder.</p>
      </section>
    ` : ""}
    ${trainedNotes.length ? renderListSection("Context AI đã dùng", trainedNotes, true) : ""}
    ${payload.training_stats ? renderTokenBudget(payload.training_stats) : ""}
  `;
}

function normalizeReportStatus(status, risk) {
  const value = String(status || "").toLowerCase();
  if (["pass", "passed", "ok", "success"].includes(value)) return "PASS";
  if (["fail", "failed", "error", "critical"].includes(value)) return "FAIL";
  if (["warning", "warn", "medium"].includes(value)) return "WARNING";
  if (String(risk || "").toLowerCase() === "high") return "FAIL";
  if (String(risk || "").toLowerCase() === "medium") return "WARNING";
  return "PASS";
}

function renderMetricCard(label, value) {
  return `
    <div>
      <span class="summary-label">${escapeHtml(label)}</span>
      <strong>${escapeHtml(value)}</strong>
    </div>
  `;
}

function renderListSection(title, items, muted = false) {
  return `
    <section class="analysis-section${muted ? " analysis-section-muted" : ""}">
      <h3>${escapeHtml(title)}</h3>
      <ul>${items.map(line => `<li>${escapeHtml(line)}</li>`).join("")}</ul>
    </section>
  `;
}

function renderTokenBudget(stats) {
  return `
    <section class="analysis-section analysis-section-muted">
      <h3>Token budget</h3>
      <ul>
        <li>Mode: ${escapeHtml(stats.mode)}</li>
        <li>Input: ${escapeHtml(stats.estimated_input_tokens)} / ${escapeHtml(stats.max_input_tokens)} tokens</li>
        <li>Failure samples: ${escapeHtml(stats.failure_samples)}</li>
      </ul>
    </section>
  `;
}

async function readJsonUpload(input, fallbackName) {
  const file = input?.files?.[0];
  if (!file) return null;
  const text = await file.text();
  return {
    name: file.name || fallbackName,
    content: text,
  };
}

async function refreshState() {
  const data = await apiFetch("/api/state");
  backendInput.value = data.backend_default || "";
  frontendInput.value = data.frontend_default || "";
  applyAiSettings(data.ai_settings || {});
  const loaded = data.uploaded_collection?.exists ? data.uploaded_collection : data.existing_output;
  renderSummary(loaded?.summary, loaded?.path);
  renderCollection(loaded?.preview || []);
  renderApiResults([], null);
  renderAnalysis(null);
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

chooseBackendButton.addEventListener("click", () => {
  selectPath({
    kind: "directory",
    title: "Select Laravel backend folder",
    input: backendInput,
  });
});

chooseFrontendButton.addEventListener("click", () => {
  selectPath({
    kind: "directory",
    title: "Select frontend folder",
    input: frontendInput,
  });
});

aiProviderSelect.addEventListener("change", () => {
  const defaults = {
    groq: "llama-3.1-8b-instant",
    ollama: "qwen2.5-coder:7b",
    openai: "gpt-4o-mini",
    gemini: "gemini-2.5-flash",
  };
  aiModelInput.value = defaults[aiProviderSelect.value] || aiModelInput.value;
  currentAiHasKey = aiProviderSelect.value === "ollama" ? true : false;
  aiKeyStatus.textContent = aiProviderSelect.value === "ollama" ? t("aiConfigured") : t("aiMissingKey");
  aiKeyStatus.classList.toggle("is-ok", aiProviderSelect.value === "ollama");
});

toggleApiKeyButton.addEventListener("click", () => {
  const isHidden = aiApiKeyInput.type === "password";
  aiApiKeyInput.type = isHidden ? "text" : "password";
  toggleApiKeyButton.textContent = isHidden ? t("hideSecret") : t("showSecret");
});

saveAiSettingsButton.addEventListener("click", async () => {
  setBusy(true, saveAiSettingsButton);
  setStatus("running", "Saving AI Settings", "Writing ai/.env...");
  try {
    const data = await apiFetch("/api/save_ai_settings", {
      method: "POST",
      body: JSON.stringify({
        provider: aiProviderSelect.value,
        model: aiModelInput.value.trim(),
        api_key: aiApiKeyInput.value.trim(),
      }),
    });
    if (!data.ok) throw new Error(data.message || "Cannot save AI settings.");
    applyAiSettings(data.settings || {});
    setStatus("success", "AI Settings Saved", data.message);
  } catch (e) {
    setStatus("error", "AI Settings Error", e.message);
  } finally {
    setBusy(false, saveAiSettingsButton);
  }
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

async function downloadCurrentCollection() {
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
}

if (downloadCollectionButton) {
  downloadCollectionButton.addEventListener("click", downloadCurrentCollection);
}

if (downloadGeneratedButton) {
  downloadGeneratedButton.addEventListener("click", downloadCurrentCollection);
}

async function loadCollectionFile(file, sourceButton = null) {
  if (!file) {
    setStatus("error", "No File", "Please select a JSON collection.");
    return null;
  }

  if (sourceButton) setBusy(true, sourceButton);
  setStatus("running", "Loading Collection", file.name);
  try {
    const content = await file.text();
    const data = await apiFetch("/api/load_collection", {
      method: "POST",
      body: JSON.stringify({ filename: file.name, content }),
    });
    if (!data.ok) throw new Error(data.message || "Cannot load collection.");
    renderSummary(data.summary, data.path);
    renderCollection(data.preview || []);
    setStatus("success", "Collection Loaded", `${data.summary?.api_count || 0} APIs ready.`);
    return data;
  } catch (e) {
    setStatus("error", "Load Error", e.message);
    return null;
  } finally {
    if (sourceButton) setBusy(false, sourceButton);
  }
}

scanButton.addEventListener("click", async () => {
  setBusy(true, scanButton, "scanning");
  setStatus("running", "scanning", "Reading backend architecture...");
  clearTerminal();
  expandTerminal();
  appendLog(">>> Starting route scan...");
  try {
    let finalData = null;
    await streamFetch("/api/scan", {
      method: "POST",
      body: JSON.stringify({ backend_src: backendInput.value.trim() }),
    }, appendLog, (result) => {
      finalData = result;
    });

    if (finalData && finalData.ok) {
      renderRoutes(finalData.routes || []);
      setStatus("success", "Scan Complete", `Found ${finalData.count} routes.`);
      appendLog(`>>> Scan complete. Found ${finalData.count} routes.`);
    } else {
      setStatus("error", "Scan Error", finalData?.message || "Scan failed.");
      appendLog(`>>> Scan failed: ${finalData?.message || "Unknown error."}`);
    }
  } catch (e) {
    setStatus("error", "Scan Error", e.message);
    appendLog(`>>> Scan Error: ${e.message}`);
  } finally {
    setBusy(false, scanButton);
  }
});

document.querySelector("#generator-form").addEventListener("submit", async (e) => {
  e.preventDefault();
  if (!dryRunInput.checked && !currentAiHasKey) {
    setStatus("error", "Missing AI Key", "Save API key in AI Settings or enable Dry Run.");
    return;
  }
  setBusy(true, generateButton, "generating");
  setStatus("running", "generating", "Building test vectors...");
  clearTerminal();
  expandTerminal();
  appendLog(">>> Starting testcase generation...");
  try {
    const payload = {
      backend_src: backendInput.value.trim(),
      frontend_src: frontendInput.value.trim(),
      limit: limitInput.value.trim(),
      dry_run: dryRunInput.checked,
      provider: aiProviderSelect.value,
      model: aiModelInput.value.trim(),
    };
    let finalData = null;
    await streamFetch("/api/generate", {
      method: "POST",
      body: JSON.stringify(payload),
    }, appendLog, (result) => {
      finalData = result;
    });

    if (finalData && finalData.ok) {
      const output = await apiFetch("/api/output");
      renderSummary(output.summary, finalData.output_file || output.path);
      renderCollection(output.preview || []);
      setStatus("success", "Generation Complete", finalData.output_file || "Collection ready.");
      appendLog(`>>> Generation complete. Collection output: ${finalData.output_file}`);
    } else {
      setStatus("error", "Generation Error", finalData?.message || "Generation failed.");
      appendLog(`>>> Generation failed: ${finalData?.message || "Unknown error."}`);
    }
  } catch (e) {
    setStatus("error", "Generation Error", e.message);
    appendLog(`>>> Generation Error: ${e.message}`);
  } finally {
    setBusy(false, generateButton);
  }
});

loadCollectionButton.addEventListener("click", async () => {
  const file = collectionFileInput.files?.[0];
  await loadCollectionFile(file, loadCollectionButton);
});

runCollectionFileInput.addEventListener("change", async () => {
  await loadCollectionFile(runCollectionFileInput.files?.[0]);
});

runApiButton.addEventListener("click", async () => {
  setBusy(true, runApiButton, "runningApi");
  setStatus("running", "runningApi", "Executing test cases...");
  clearTerminal();
  expandTerminal();
  appendLog(">>> Starting API test run...");
  try {
    let finalData = null;
    await streamFetch("/api/run_api_tests", {
      method: "POST",
      body: JSON.stringify({ base_url: apiBaseUrlInput.value.trim() }),
    }, appendLog, (result) => {
      finalData = result;
    });

    if (finalData && finalData.ok) {
      renderApiResults(finalData.results || [], finalData.summary);
      currentResultPath = finalData.result_file || "";
      currentRawResult = finalData;
      apiResultRaw.textContent = JSON.stringify(finalData, null, 2);
      apiResultRaw.classList.add("screen-hidden");
      apiResults.classList.remove("screen-hidden");
      downloadResultButton.disabled = !currentResultPath;
      toggleRawResultButton.disabled = false;
      toggleRawResultButton.textContent = t("rawJson");
      setStatus(finalData.summary?.failed > 0 ? "error" : "success", "API Suite Complete", `${finalData.summary?.passed}/${finalData.summary?.total} passed.`);
      appendLog(`>>> API suite run complete. Passed ${finalData.summary?.passed}/${finalData.summary?.total}.`);
    } else {
      setStatus("error", "Execution Error", finalData?.message || "API suite execution failed.");
      appendLog(`>>> API suite execution failed: ${finalData?.message || "Unknown error."}`);
    }
  } catch (e) {
    setStatus("error", "Execution Error", e.message);
    appendLog(`>>> API Execution Error: ${e.message}`);
  } finally {
    setBusy(false, runApiButton);
  }
});

downloadResultButton.addEventListener("click", async () => {
  if (!currentResultPath) return;
  try {
    const res = await fetch(`/api/download_output?path=${encodeURIComponent(currentResultPath)}`);
    if (!res.ok) {
      const text = await res.text();
      throw new Error(text || `HTTP ${res.status}`);
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = currentResultPath.split("/").pop() || "api-test-results.json";
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    setStatus("success", "Result Ready", "Downloaded API test result JSON.");
  } catch (e) {
    setStatus("error", "Download Error", e.message);
  }
});

analyzeResultButton.addEventListener("click", async () => {
  setBusy(true, analyzeResultButton, "analyzingResults");
  setStatus("running", "analyzingResults", "Building tester report from uploaded result JSON...");
  clearTerminal();
  expandTerminal();
  appendLog(">>> Starting Groq results analysis...");
  try {
    const apiResult = await readJsonUpload(analysisApiResultFile, "api-test-results.json");
    const playwrightReport = await readJsonUpload(analysisPlaywrightResultFile, "playwright-results.json");
    
    let finalData = null;
    await streamFetch("/api/analyze_results", {
      method: "POST",
      body: JSON.stringify({
        api_result_file: apiResult,
        playwright_report_file: playwrightReport,
      }),
    }, appendLog, (result) => {
      finalData = result;
    });

    if (finalData && finalData.ok) {
      renderAnalysis(finalData);
      setStatus("success", "Analysis Complete", "Groq returned tester-style result report.");
      appendLog(">>> Analysis complete. Report rendered on screen.");
    } else {
      setStatus("error", "Analysis Error", finalData?.message || "Analysis failed.");
      appendLog(`>>> Analysis failed: ${finalData?.message || "Unknown error."}`);
    }
  } catch (e) {
    setStatus("error", "Analysis Error", e.message);
    appendLog(`>>> Analysis Error: ${e.message}`);
  } finally {
    setBusy(false, analyzeResultButton);
  }
});

downloadAnalysisButton.addEventListener("click", async () => {
  if (!currentAnalysisPath) return;
  try {
    const res = await fetch(`/api/download_output?path=${encodeURIComponent(currentAnalysisPath)}`);
    if (!res.ok) {
      const text = await res.text();
      throw new Error(text || `HTTP ${res.status}`);
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = currentAnalysisPath.split("/").pop() || "api-result-analysis.json";
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    setStatus("success", "Result Ready", "Downloaded analysis JSON.");
  } catch (e) {
    setStatus("error", "Download Error", e.message);
  }
});

toggleRawResultButton.addEventListener("click", () => {
  if (!currentRawResult) return;
  const showingRaw = !apiResultRaw.classList.contains("screen-hidden");
  apiResultRaw.classList.toggle("screen-hidden", showingRaw);
  apiResults.classList.toggle("screen-hidden", !showingRaw);
  toggleRawResultButton.textContent = showingRaw ? t("rawJson") : t("parsedView");
});

runUiButton.addEventListener("click", async () => {
  setBusy(true, runUiButton, "runningUi");
  setStatus("running", "runningUi", "Launching Playwright...");
  uiResults.textContent = ">>> PLAYWRIGHT EXECUTION STARTED...";
  clearTerminal();
  expandTerminal();
  appendLog(">>> Starting Playwright UI smoke tests run...");
  try {
    let finalData = null;
    await streamFetch("/api/run_ui_smoke", {
      method: "POST",
      body: JSON.stringify({
        headed: showPlaywrightUiInput.checked,
        site_base_url: siteBaseUrlInput.value.trim(),
      }),
    }, appendLog, (result) => {
      finalData = result;
    });

    if (finalData && finalData.ok) {
      const reportSummary = finalData.report
        ? `\n\n>>> PLAYWRIGHT JSON REPORT\n${JSON.stringify(finalData.report.stats || finalData.report, null, 2)}\nReport file: ${finalData.report_file || "-"}`
        : "";
      const modeSummary = `Mode: ${finalData.headed ? "headed browser" : "headless"}\nCommand: ${finalData.command || "-"}`;
      uiResults.textContent = [modeSummary, finalData.stdout, finalData.stderr, reportSummary].filter(Boolean).join("\n").trim();
      currentPlaywrightReportPath = finalData.report_file || "";
      downloadPlaywrightReportButton.disabled = !currentPlaywrightReportPath;
      setStatus(finalData.ok ? "success" : "error", finalData.ok ? "UI Tests Passed" : "UI Tests Failed", "See output for details.");
      appendLog(`>>> Playwright UI run completed: ${finalData.ok ? 'SUCCESS' : 'FAILED'}`);
    } else {
      uiResults.textContent = `>>> ERROR: ${finalData?.message || "Unknown execution error."}`;
      setStatus("error", "UI Automation Error", finalData?.message || "UI tests execution failed.");
      appendLog(`>>> Playwright UI execution failed: ${finalData?.message || "Unknown error."}`);
    }
  } catch (e) {
    uiResults.textContent = `>>> ERROR: ${e.message}`;
    setStatus("error", "UI Automation Error", e.message);
    appendLog(`>>> Playwright UI Execution Error: ${e.message}`);
  } finally {
    setBusy(false, runUiButton);
  }
});

downloadPlaywrightReportButton.addEventListener("click", async () => {
  if (!currentPlaywrightReportPath) return;
  try {
    const res = await fetch(`/api/download_output?path=${encodeURIComponent(currentPlaywrightReportPath)}`);
    if (!res.ok) {
      const text = await res.text();
      throw new Error(text || `HTTP ${res.status}`);
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = currentPlaywrightReportPath.split("/").pop() || "playwright-results.json";
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    setStatus("success", "Report Ready", "Downloaded Playwright report JSON.");
  } catch (e) {
    setStatus("error", "Download Error", e.message);
  }
});

// Terminal wire listeners
if (clearTerminalBtn) {
  clearTerminalBtn.addEventListener("click", clearTerminal);
}
if (toggleTerminalBtn) {
  toggleTerminalBtn.addEventListener("click", toggleTerminal);
}

// Initialize terminal state to collapsed by default
if (terminalSection) {
  terminalSection.classList.add("collapsed");
  updateTerminalToggleLabel();
}

applyTheme();
applyTranslations();
applyScreenMode();
applySidebarTab();
refreshState().catch(console.error);
languageSelect.value = currentLanguage;
