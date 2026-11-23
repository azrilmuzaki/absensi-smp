/*
 * ========================================================
 * SISTEM ABSENSI RFID - ESP32 Firmware
 * ========================================================
 * Hardware  : ESP32 DevKit + MFRC522 + LCD I2C 16x2 + Buzzer
 * Server    : Laravel (REST API)
 * Storage   : LittleFS untuk cache lokal siswa + offline queue
 * 
 * PIN Layout:
 *   MFRC522: SS=5, RST=27, SCK=18, MOSI=23, MISO=19
 *   LCD I2C : SDA=21, SCL=22
 *   Buzzer  : GPIO 25
 * ========================================================
 */

#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <Preferences.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <ArduinoJson.h>
#include <LittleFS.h>
#include <SPI.h>
#include <MFRC522.h>
#include <LiquidCrystal_I2C.h>
#include <time.h>

// ── Konfigurasi WiFi & Server ─────────────────────────────
#include "secrets.h"

// ── File Path LittleFS ────────────────────────────────────
#define CACHE_FILE    "/students.json"
#define QUEUE_FILE    "/queue.json"
#define SETTINGS_FILE "/settings.json"

// ── PIN ───────────────────────────────────────────────────
#define SS_PIN        5
#define RST_PIN       27
#define BUZZER_PIN    25

// ── Konstanta ─────────────────────────────────────────────
#define HEARTBEAT_INTERVAL  60000   // 60 detik
#define SYNC_INTERVAL       300000  // 5 menit
#define MAX_QUEUE_SIZE      500
#define LCD_COLS            16
#define LCD_ROWS            2
#define WIFI_SETUP_BUTTON   0
#define WIFI_SETUP_HOLD_MS  5000

// ── NTP ───────────────────────────────────────────────────
#define NTP_SERVER    "pool.ntp.org"
#define TZ_OFFSET     25200         // UTC+7 (7 * 3600)

// ── Objects ───────────────────────────────────────────────
MFRC522         rfid(SS_PIN, RST_PIN);
LiquidCrystal_I2C lcd(0x27, LCD_COLS, LCD_ROWS);
Preferences     wifiPreferences;
WebServer       wifiSetupServer(80);
DNSServer       wifiSetupDns;

// ── State ─────────────────────────────────────────────────
bool isOnline               = false;
bool needsSync              = true;
unsigned long lastHeartbeat = 0;
unsigned long lastSync      = 0;
unsigned long lastScanBlock = 0;     // Anti double-tap (debounce 3 detik)
String lastUID              = "";
bool wifiSetupPortalActive  = false;
bool wifiSetupButtonHeld    = false;
bool wifiRestartPending     = false;
unsigned long wifiSetupButtonStartedAt = 0;
unsigned long wifiRestartAt = 0;
unsigned long wifiDisconnectedAt = 0;
unsigned long lastWifiReconnectAt = 0;
String wifiSetupSsid;
String wifiSetupPassword;

// ─────────────────────────────────────────────────────────
// Struktur cache siswa lokal
// ─────────────────────────────────────────────────────────
struct Student {
    String uid;
    int    sid;
    String name;
    String className;
};
Student studentCache[500];
int     cacheCount = 0;

// ─────────────────────────────────────────────────────────
// Struktur antrian offline
// ─────────────────────────────────────────────────────────
struct QueueItem {
    String uid;
    String timestamp;
    String idempotencyKey;
};
QueueItem offlineQueue[MAX_QUEUE_SIZE];
int       queueCount = 0;

// ─────────────────────────────────────────────────────────
// Jam absensi (default, akan di-overwrite saat sync)
// ─────────────────────────────────────────────────────────
String jamMasuk     = "06:00:00";
String jamTerlambat = "07:05:00";
String batasScan    = "10:00:00";

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// UTILITAS
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

String getTimestamp() {
    struct tm timeinfo;
    if (!getLocalTime(&timeinfo)) return "";
    char buf[30];
    strftime(buf, sizeof(buf), "%Y-%m-%dT%H:%M:%S+07:00", &timeinfo);
    return String(buf);
}

String getTimeOnly() {
    struct tm timeinfo;
    if (!getLocalTime(&timeinfo)) return "00:00:00";
    char buf[10];
    strftime(buf, sizeof(buf), "%H:%M:%S", &timeinfo);
    return String(buf);
}

String makeIdempotencyKey(String uid, String ts) {
    // Format: UID-YYYY-MM-DD  (unik per kartu per hari)
    return uid + "-" + ts.substring(0, 10);
}

void buzz(int count, int durationMs = 100) {
    for (int i = 0; i < count; i++) {
        digitalWrite(BUZZER_PIN, HIGH);
        delay(durationMs);
        digitalWrite(BUZZER_PIN, LOW);
        if (i < count - 1) delay(80);
    }
}

String normalizeLcdText(const String& text) {
    String normalized;
    normalized.reserve(text.length());

    size_t index = 0;
    while (index < text.length()) {
        const uint8_t character = static_cast<uint8_t>(text[index]);

        if (character >= 0x80) {
            normalized += '?';
            if ((character & 0xE0) == 0xC0) {
                index += 2;
            } else if ((character & 0xF0) == 0xE0) {
                index += 3;
            } else if ((character & 0xF8) == 0xF0) {
                index += 4;
            } else {
                index++;
            }
            continue;
        }

        char printable = static_cast<char>(character);
        if (printable < ' ' || printable > '~') {
            printable = ' ';
        } else if (printable >= 'a' && printable <= 'z') {
            printable -= ('a' - 'A');
        }

        normalized += printable;
        index++;
    }

    return normalized;
}

String formatLcdLine(const String& text) {
    String line = normalizeLcdText(text);

    if (line.length() > LCD_COLS) {
        line = line.substring(0, LCD_COLS);
        line.setCharAt(LCD_COLS - 1, '>');
    }

    while (line.length() < LCD_COLS) {
        line += ' ';
    }

    return line;
}

String lcdScrollWindow(const String& text, size_t offset) {
    String line;
    line.reserve(LCD_COLS);

    for (size_t column = 0; column < LCD_COLS; column++) {
        const size_t position = offset + column;
        line += position < text.length() ? text[position] : ' ';
    }

    return line;
}

void lcdPrint(String line1, String line2 = "") {
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print(formatLcdLine(line1));
    lcd.setCursor(0, 1);
    lcd.print(formatLcdLine(line2));
}

void lcdPrintAttendanceResult(const String& name, const String& status) {
    const String normalizedName = normalizeLcdText(name);

    if (normalizedName.length() <= LCD_COLS) {
        lcdPrint(normalizedName, status);
        delay(2500);
        return;
    }

    const size_t finalOffset = normalizedName.length() - LCD_COLS;
    const unsigned long displayDuration = max(2500UL, static_cast<unsigned long>(finalOffset) * 150UL + 100UL);
    const unsigned long displayStartedAt = millis();
    unsigned long lastScrollAt = displayStartedAt;
    size_t offset = 0;

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print(lcdScrollWindow(normalizedName, offset));
    lcd.setCursor(0, 1);
    lcd.print(formatLcdLine(status));

    while (millis() - displayStartedAt < displayDuration) {
        const unsigned long currentTime = millis();

        if (offset < finalOffset && currentTime - lastScrollAt >= 150) {
            lastScrollAt = currentTime;
            offset++;
            lcd.setCursor(0, 0);
            lcd.print(lcdScrollWindow(normalizedName, offset));
        }

        delay(1);
    }
}

void showReadyScreen() {
    if (wifiSetupPortalActive) {
        lcdPrint("SSID:" + wifiSetupSsid, "PASS:" + wifiSetupPassword);
        return;
    }

    lcdPrint("SISTEM ABSENSI", isOnline ? "SMP WAHID HASYIM" : "SMP WAHID HASYIM");
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// WIFI
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

bool loadSavedWifiCredentials(String& ssid, String& password) {
    if (!wifiPreferences.begin("wifi_config", true)) {
        return false;
    }

    ssid = wifiPreferences.getString("ssid", "");
    password = wifiPreferences.getString("password", "");
    wifiPreferences.end();

    return ssid.length() > 0;
}

bool saveWifiCredentials(const String& ssid, const String& password) {
    if (!wifiPreferences.begin("wifi_config", false)) {
        return false;
    }

    const size_t savedSsid = wifiPreferences.putString("ssid", ssid);
    const size_t savedPassword = wifiPreferences.putString("password", password);
    wifiPreferences.end();

    return savedSsid > 0 && savedPassword > 0;
}

bool connectWifiWithCredentials(const String& ssid, const String& password) {
    if (ssid.length() == 0) {
        return false;
    }

    WiFi.disconnect();
    delay(150);
    WiFi.mode(WIFI_STA);
    WiFi.begin(ssid.c_str(), password.c_str());
    lcdPrint("SAMBUNG WIFI", "WIFI: " + ssid);

    const unsigned long startedAt = millis();
    while (WiFi.status() != WL_CONNECTED && millis() - startedAt < 12000) {
        delay(300);
    }

    return WiFi.status() == WL_CONNECTED;
}

String escapeHtml(const String& value) {
    String escaped;
    escaped.reserve(value.length());

    for (size_t index = 0; index < value.length(); index++) {
        switch (value[index]) {
            case '&': escaped += "&amp;"; break;
            case '<': escaped += "&lt;"; break;
            case '>': escaped += "&gt;"; break;
            case '"': escaped += "&quot;"; break;
            case '\'': escaped += "&#39;"; break;
            default: escaped += value[index]; break;
        }
    }

    return escaped;
}

String buildWifiSetupPage(const String& notice = "") {
    String page = R"html(<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0f766e"><title>Pengaturan Wi-Fi Absensi</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:24px;background:#f1f5f9;color:#0f172a;font:16px/1.5 Arial,sans-serif;display:grid;place-items:center}
main{width:min(100%,460px)}.card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:26px;box-shadow:0 14px 40px #0f172a12}
.tag{display:inline-block;padding:5px 10px;border-radius:999px;background:#ccfbf1;color:#115e59;font-weight:700;font-size:12px;letter-spacing:.06em}
h1{font-size:25px;line-height:1.2;margin:16px 0 8px}p{color:#475569;margin:0 0 20px}.field{display:block;margin:18px 0 7px;font-weight:700;font-size:14px}
input{width:100%;height:48px;border:1px solid #cbd5e1;border-radius:11px;padding:0 13px;font:inherit;background:#fff}input:focus{outline:3px solid #99f6e4;border-color:#0f766e}
button{width:100%;height:50px;margin-top:22px;border:0;border-radius:11px;background:#0f766e;color:#fff;font:700 16px Arial,sans-serif;cursor:pointer}button:active{transform:translateY(1px)}
.notice{padding:12px 14px;margin:16px 0;border-radius:10px;background:#fef3c7;color:#92400e}.hint{font-size:13px;margin:16px 0 0}
</style></head><body><main><section class="card"><span class="tag">PENGATURAN PERANGKAT</span>
<h1>Sambungkan ke Wi-Fi sekolah</h1><p>Pilih jaringan Wi-Fi, lalu masukkan password-nya. Perangkat akan tersambung ulang otomatis.</p>)html";

    if (notice.length() > 0) {
        page += "<div class=\"notice\">" + escapeHtml(notice) + "</div>";
    }

    page += R"html(<form method="post" action="/save">
<label class="field" for="ssid">Nama Wi-Fi</label><input id="ssid" name="ssid" list="wifi-list" maxlength="32" placeholder="Pilih atau ketik nama Wi-Fi" autocomplete="off" required>
<datalist id="wifi-list">)html";

    const int networkCount = WiFi.scanNetworks();
    for (int index = 0; index < networkCount; index++) {
        const String ssid = WiFi.SSID(index);
        if (ssid.length() == 0) {
            continue;
        }

        page += "<option value=\"" + escapeHtml(ssid) + "\">";
    }
    WiFi.scanDelete();

    page += R"html(</datalist><label class="field" for="password">Password Wi-Fi</label>
<input id="password" name="password" type="password" maxlength="63" autocomplete="current-password" placeholder="Masukkan password Wi-Fi">
<button type="submit">Simpan dan sambungkan</button></form>
<p class="hint">Password Wi-Fi disimpan di perangkat absensi.</p></section></main></body></html>)html";

    return page;
}

void startWifiSetupPortal() {
    if (wifiSetupPortalActive) {
        return;
    }

    WiFi.mode(WIFI_AP_STA);

    // NAMA DAN PASSWORD WIFI ESP32
    wifiSetupSsid = "ABSENSI-SMP-WH";
    wifiSetupPassword = "absensiwh";

    const IPAddress portalIp(192, 168, 4, 1);
    const IPAddress subnet(255, 255, 255, 0);
    WiFi.softAPConfig(portalIp, portalIp, subnet);

    if (!WiFi.softAP(wifiSetupSsid.c_str(), wifiSetupPassword.c_str())) {
        Serial.println("[WIFI SETUP] Gagal membuka access point.");
        lcdPrint("SETUP WIFI GAGAL", "RESTART PERANGKAT");
        return;
    }

    wifiSetupDns.start(53, "*", portalIp);
    wifiSetupServer.on("/", HTTP_GET, []() {
        wifiSetupServer.sendHeader("Cache-Control", "no-store");
        wifiSetupServer.send(200, "text/html; charset=utf-8", buildWifiSetupPage());
    });

    wifiSetupServer.on("/save", HTTP_POST, []() {
        const String ssid = wifiSetupServer.arg("ssid");
        const String password = wifiSetupServer.arg("password");

        if (ssid.length() == 0 || ssid.length() > 32 || password.length() > 63) {
            wifiSetupServer.send(400, "text/html; charset=utf-8", buildWifiSetupPage("Nama atau password Wi-Fi tidak valid."));
            return;
        }

        if (!saveWifiCredentials(ssid, password)) {
            wifiSetupServer.send(500, "text/html; charset=utf-8", buildWifiSetupPage("Pengaturan gagal disimpan. Coba lagi."));
            return;
        }

        wifiSetupServer.send(200, "text/html; charset=utf-8", buildWifiSetupPage("Pengaturan tersimpan. Perangkat akan menyambung ulang."));
        lcdPrint("WIFI DISIMPAN", "PERANGKAT RESTART");
        wifiRestartPending = true;
        wifiRestartAt = millis() + 1500;
    });

    wifiSetupServer.onNotFound([]() {
        wifiSetupServer.sendHeader("Location", "/", true);
        wifiSetupServer.send(302, "text/plain", "Buka pengaturan Wi-Fi");
    });

    wifiSetupServer.begin();

    wifiSetupPortalActive = true;
    Serial.println("[WIFI SETUP] Portal siap di http://192.168.4.1");
    showReadyScreen();
}

bool connectWifi() {
    String savedSsid;
    String savedPassword;
    const bool hasSavedCredentials = loadSavedWifiCredentials(savedSsid, savedPassword);
    bool connected = hasSavedCredentials && connectWifiWithCredentials(savedSsid, savedPassword);

    const String fallbackSsid = WIFI_SSID;
    const String fallbackPassword = WIFI_PASSWORD;
    if (!connected && !hasSavedCredentials && fallbackSsid.length() > 0) {
        connected = connectWifiWithCredentials(fallbackSsid, fallbackPassword);
        if (connected) {
            saveWifiCredentials(fallbackSsid, fallbackPassword);
        }
    }

    isOnline = connected;
    if (isOnline) {
        wifiDisconnectedAt = 0;
        configTime(TZ_OFFSET, 0, NTP_SERVER);
        lcdPrint("WIFI TERHUBUNG", WiFi.localIP().toString());
        delay(1500);
    } else {
        lcdPrint("WIFI TIDAK ADA", "BUKA SETUP WIFI");
        delay(1000);
        startWifiSetupPortal();
    }

    return isOnline;
}

void handleWifiSetupButton() {
    if (digitalRead(WIFI_SETUP_BUTTON) != LOW) {
        if (wifiSetupButtonHeld) {
            wifiSetupButtonHeld = false;
            wifiSetupButtonStartedAt = 0;
            if (!wifiSetupPortalActive) {
                showReadyScreen();
            }
        }
        return;
    }

    if (!wifiSetupButtonHeld) {
        wifiSetupButtonHeld = true;
        wifiSetupButtonStartedAt = millis();
        lcdPrint("SETUP WIFI", "TAHAN BOOT 5 DTK");
        return;
    }

    if (!wifiSetupPortalActive && millis() - wifiSetupButtonStartedAt >= WIFI_SETUP_HOLD_MS) {
        startWifiSetupPortal();
    }
}

void serviceWifiSetupPortal() {
    if (!wifiSetupPortalActive) {
        return;
    }

    wifiSetupDns.processNextRequest();
    wifiSetupServer.handleClient();
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// LITTLEFS: Load & Save Cache
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

void loadCacheFromFS() {
    if (!LittleFS.exists(CACHE_FILE)) return;

    File f = LittleFS.open(CACHE_FILE, "r");
    if (!f) return;

    DynamicJsonDocument doc(65536);
    DeserializationError err = deserializeJson(doc, f);
    f.close();

    if (err) { Serial.println("Cache JSON error: " + String(err.c_str())); return; }

    cacheCount = 0;
    JsonArray arr = doc["students"].as<JsonArray>();
    for (JsonObject s : arr) {
        if (cacheCount >= 500) break;
        studentCache[cacheCount] = {
            s["uid"].as<String>(),
            s["sid"].as<int>(),
            s["name"].as<String>(),
            s["class"].as<String>()
        };
        cacheCount++;
    }

    // Load settings
    if (doc.containsKey("settings")) {
        jamMasuk     = doc["settings"]["jam_masuk"].as<String>();
        jamTerlambat = doc["settings"]["jam_terlambat"].as<String>();
        batasScan    = doc["settings"]["batas_scan"].as<String>();
    }

    Serial.println("Cache loaded: " + String(cacheCount) + " siswa.");
}

void saveCacheToFS(String jsonPayload) {
    File f = LittleFS.open(CACHE_FILE, "w");
    if (f) { f.print(jsonPayload); f.close(); }
}

void loadQueueFromFS() {
    if (!LittleFS.exists(QUEUE_FILE)) return;

    File f = LittleFS.open(QUEUE_FILE, "r");
    if (!f) return;

    DynamicJsonDocument doc(32768);
    deserializeJson(doc, f);
    f.close();

    queueCount = 0;
    for (JsonObject item : doc["queue"].as<JsonArray>()) {
        if (queueCount >= MAX_QUEUE_SIZE) break;
        offlineQueue[queueCount++] = {
            item["uid"].as<String>(),
            item["timestamp"].as<String>(),
            item["idempotency_key"].as<String>()
        };
    }
    Serial.println("Queue loaded: " + String(queueCount) + " item.");
}

void saveQueueToFS() {
    DynamicJsonDocument doc(32768);
    JsonArray arr = doc.createNestedArray("queue");
    for (int i = 0; i < queueCount; i++) {
        JsonObject item = arr.createNestedObject();
        item["uid"]             = offlineQueue[i].uid;
        item["timestamp"]       = offlineQueue[i].timestamp;
        item["idempotency_key"] = offlineQueue[i].idempotencyKey;
    }
    File f = LittleFS.open(QUEUE_FILE, "w");
    if (f) { serializeJson(doc, f); f.close(); }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// CEK CACHE LOKAL (offline mode)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Student* findStudentByUID(String uid) {
    for (int i = 0; i < cacheCount; i++) {
        if (studentCache[i].uid == uid) return &studentCache[i];
    }
    return nullptr;
}

bool isInAbsenceWindow() {
    String timeNow = getTimeOnly();
    return timeNow >= jamMasuk && timeNow <= batasScan;
}

bool isLate() {
    String timeNow = getTimeOnly();
    return timeNow > jamTerlambat;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// HTTP Helpers
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

String httpPost(String endpoint, String jsonBody) {
    if (WiFi.status() != WL_CONNECTED) return "";
    HTTPClient http;
    http.begin(String(SERVER_URL) + endpoint);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-Device-Key", DEVICE_API_KEY);
    http.setTimeout(5000);
    int code = http.POST(jsonBody);
    String resp = (code > 0) ? http.getString() : "";
    http.end();
    return resp;
}

String httpGet(String endpoint) {
    if (WiFi.status() != WL_CONNECTED) return "";
    HTTPClient http;
    http.begin(String(SERVER_URL) + endpoint);
    http.addHeader("X-Device-Key", DEVICE_API_KEY);
    http.setTimeout(8000);
    int code = http.GET();
    String resp = (code > 0) ? http.getString() : "";
    http.end();
    return resp;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// SYNC DATA DARI SERVER
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

bool syncFromServer() {
    Serial.println("[SYNC] Mengunduh data dari server...");
    lcdPrint("AMBIL DATA SISWA", "MOHON TUNGGU");

    String resp = httpGet("/api/sync");
    if (resp == "") {
        Serial.println("[SYNC] Gagal: server tidak respons.");
        lcdPrint("SERVER TAK JAWAB", "CEK INTERNET");
        delay(1500);
        return false;
    }

    DynamicJsonDocument doc(65536);
    if (deserializeJson(doc, resp) != DeserializationError::Ok || !doc["success"].as<bool>()) {
        Serial.println("[SYNC] Gagal: respons server tidak valid.");
        lcdPrint("DATA TDK VALID", "COBA LAGI");
        delay(1500);
        return false;
    }

    // Simpan ke LittleFS
    saveCacheToFS(resp);

    // Reload dari file (agar konsisten)
    loadCacheFromFS();

    lastSync = millis();
    needsSync = false;

    lcdPrint("DATA TERSINKRON", "SISWA: " + String(cacheCount));
    buzz(1, 80);
    delay(1500);
    return true;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// HEARTBEAT
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

void sendHeartbeat() {
    String resp = httpPost("/api/heartbeat", "{}");
    if (resp == "") return;

    DynamicJsonDocument doc(512);
    if (deserializeJson(doc, resp) != DeserializationError::Ok) return;

    if (doc["needs_sync"].as<bool>()) {
        Serial.println("[HB] Server minta sync ulang.");
        syncFromServer();
    }
    lastHeartbeat = millis();
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PROSES ABSENSI ONLINE
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

void processOnline(String uid, String ts) {
    String ikey = makeIdempotencyKey(uid, ts);

    DynamicJsonDocument body(512);
    body["uid"]             = uid;
    body["timestamp"]       = ts;
    body["idempotency_key"] = ikey;

    String jsonStr;
    serializeJson(body, jsonStr);

    String resp = httpPost("/api/tap", jsonStr);

    if (resp == "") {
        Serial.println("[TAP] Gagal kirim ke server → masuk antrean.");
        isOnline = false;
        // Tambah ke antrean offline
        if (queueCount < MAX_QUEUE_SIZE) {
            offlineQueue[queueCount++] = {uid, ts, ikey};
            saveQueueToFS();
        }
        // Feedback dari cache lokal
        Student* s = findStudentByUID(uid);
        if (s) {
            String status = isLate() ? "TERLAMBAT" : "HADIR";
            lcdPrint(s->name, status == "TERLAMBAT" ? "OFFLINE - TELAT" : "OFFLINE - HADIR");
            buzz(s ? 2 : 4, 100);
        } else {
            lcdPrint("KARTU TIDAK ADA", "DISIMPAN LOKAL");
            buzz(3, 150);
        }
        delay(2500);
        showReadyScreen();
        return;
    }

    DynamicJsonDocument doc(512);
    if (deserializeJson(doc, resp) != DeserializationError::Ok) {
        lcdPrint("RESPON SERVER", "GAGAL DIBACA");
        buzz(2, 200);
        delay(2500);
        showReadyScreen();
        return;
    }

    String code    = doc["code"].as<String>();
    String message = doc["message"].as<String>();
    String status  = doc["status"].as<String>();
    String name    = doc["student"].as<String>();

    Serial.println("[TAP] Code=" + code + " Name=" + name + " Status=" + status);

    if (code == "SUCCESS") {
        String line2 = (status == "HADIR") ? "HADIR - BERHASIL" : "TERLAMBAT";
        buzz(status == "HADIR" ? 1 : 2, 120);
        lcdPrintAttendanceResult(name, line2);
    } else if (code == "DUPLICATE") {
        buzz(1, 50);
        lcdPrintAttendanceResult(name, status == "TERLAMBAT" ? "SUDAH TERLAMBAT" : "SUDAH HADIR");
    } else if (code == "UNKNOWN_CARD") {
        lcdPrint("KARTU TIDAK", "TERDAFTAR");
        buzz(3, 150);
    } else if (code == "HOLIDAY") {
        lcdPrint("HARI LIBUR", "ABSENSI DITUTUP");
        buzz(2, 100);
    } else if (code == "TOO_EARLY") {
        lcdPrint("BELUM WAKTUNYA", "TUNGGU JAM MASUK");
        buzz(1, 80);
    } else if (code == "TOO_LATE") {
        lcdPrint("ABSENSI DITUTUP", "HUBUNGI GURU");
        buzz(3, 100);
    } else {
        lcdPrint("ABSEN GAGAL", message);
        buzz(2, 200);
    }

    if (code != "SUCCESS" && code != "DUPLICATE") {
        delay(2500);
    }
    showReadyScreen();
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PROSES ABSENSI OFFLINE (dari cache LittleFS)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

void processOffline(String uid, String ts) {
    Student* s = findStudentByUID(uid);

    if (!s) {
        lcdPrint("KARTU BELUM ADA", "CEK SAAT ONLINE");
        buzz(4, 150);
        delay(2500);
        showReadyScreen();
        return;
    }

    if (!isInAbsenceWindow()) {
        lcdPrint("ABSENSI TUTUP", "CEK JAM SEKOLAH");
        buzz(2, 150);
        delay(2000);
        showReadyScreen();
        return;
    }

    String ikey   = makeIdempotencyKey(uid, ts);
    String status = isLate() ? "TERLAMBAT" : "HADIR";

    // Simpan ke antrean offline untuk dikirim nanti
    if (queueCount < MAX_QUEUE_SIZE) {
        offlineQueue[queueCount++] = {uid, ts, ikey};
        saveQueueToFS();
    }

    buzz(status == "HADIR" ? 1 : 2, 120);
    lcdPrintAttendanceResult(s->name, status == "TERLAMBAT" ? "OFFLINE - TELAT" : "OFFLINE - HADIR");
    showReadyScreen();
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FLUSH OFFLINE QUEUE
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

void flushOfflineQueue() {
    if (queueCount == 0 || !isOnline) return;
    Serial.println("[QUEUE] Mengirim " + String(queueCount) + " item ke server...");

    const int totalCount = queueCount;
    const int batchSize = 50;
    int retained = 0;
    int accepted = 0;

    for (int start = 0; start < totalCount; start += batchSize) {
        const int count = min(batchSize, totalCount - start);
        String resp;

        {
            DynamicJsonDocument doc(32768);
            JsonArray arr = doc.createNestedArray("queue");
            for (int offset = 0; offset < count; offset++) {
                const QueueItem &queued = offlineQueue[start + offset];
                JsonObject item = arr.createNestedObject();
                item["uid"] = queued.uid;
                item["timestamp"] = queued.timestamp;
                item["idempotency_key"] = queued.idempotencyKey;
            }

            if (doc.overflowed()) {
                Serial.println("[QUEUE] Memori batch penuh; antrean sisanya dipertahankan.");
                for (int i = start; i < totalCount; i++) offlineQueue[retained++] = offlineQueue[i];
                break;
            }

            String jsonStr;
            serializeJson(doc, jsonStr);
            resp = httpPost("/api/offline-queue", jsonStr);
        }

        if (resp == "") {
            for (int i = start; i < totalCount; i++) offlineQueue[retained++] = offlineQueue[i];
            break;
        }

        bool validBatchResponse = false;
        {
            DynamicJsonDocument respDoc(32768);
            if (deserializeJson(respDoc, resp) == DeserializationError::Ok && respDoc["success"].as<bool>()) {
                JsonArray results = respDoc["results"].as<JsonArray>();
                if (!results.isNull() && results.size() == count) {
                    validBatchResponse = true;
                    for (int offset = 0; offset < count; offset++) {
                        JsonObject result = results[offset].as<JsonObject>();
                        const QueueItem &queued = offlineQueue[start + offset];
                        const bool matchingKey = result["idempotency_key"].as<String>() == queued.idempotencyKey;
                        if (matchingKey && result["success"].as<bool>()) {
                            accepted++;
                        } else {
                            offlineQueue[retained++] = queued;
                        }
                    }
                }
            }
        }

        if (!validBatchResponse) {
            Serial.println("[QUEUE] Respons batch tidak valid; antrean sisanya dipertahankan.");
            for (int i = start; i < totalCount; i++) offlineQueue[retained++] = offlineQueue[i];
            break;
        }
    }

    queueCount = retained;
    saveQueueToFS();
    Serial.println("[QUEUE] " + String(accepted) + " diterima, " + String(queueCount) + " dipertahankan untuk dicoba lagi.");
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// SETUP
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

void setup() {
    Serial.begin(115200);
    pinMode(BUZZER_PIN, OUTPUT);
    pinMode(WIFI_SETUP_BUTTON, INPUT_PULLUP);

    if (!DEVICE_API_KEY[0] || !SERVER_URL[0]) {
        Serial.println("Konfigurasi DEVICE_API_KEY atau SERVER_URL belum diisi.");
        lcd.init();
        lcd.backlight();
        lcdPrint("KONFIGURASI", "ISI API KEY/URL");
        while (1) delay(1000);
    }

    // Init LCD
    lcd.init();
    lcd.backlight();
    lcdPrint("ABSENSI SMP", "MENYIAPKAN...");

    // Init SPI + RFID
    SPI.begin();
    rfid.PCD_Init();
    delay(500);

    // Init LittleFS
    if (!LittleFS.begin(true)) {
        Serial.println("LittleFS Mount Failed!");
        lcdPrint("MEMORI ERROR", "CEK LITTLEFS");
        while (1) delay(1000);
    }

    // Load cache & queue dari penyimpanan lokal
    loadCacheFromFS();
    loadQueueFromFS();

    // Hubungkan WiFi
    isOnline = connectWifi();

    if (isOnline) {
        syncFromServer();
        flushOfflineQueue();
        lastHeartbeat = millis();
        lastSync      = millis();
    }

    showReadyScreen();
    delay(1000);
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// MAIN LOOP
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

void loop() {
    unsigned long now = millis();

    serviceWifiSetupPortal();
    handleWifiSetupButton();

    if (wifiRestartPending && static_cast<long>(now - wifiRestartAt) >= 0) {
        ESP.restart();
    }

    // Cek koneksi WiFi & reconnect otomatis
    if (WiFi.status() != WL_CONNECTED) {
        if (isOnline) {
            isOnline = false;
            wifiDisconnectedAt = now;
            lastWifiReconnectAt = now;
            lcdPrint("WIFI TERPUTUS", "MODE OFFLINE");
            delay(1000);
            showReadyScreen();
        }

        if (wifiDisconnectedAt == 0) {
            wifiDisconnectedAt = now;
            lastWifiReconnectAt = now;
        }

        if (!wifiSetupPortalActive && now - lastWifiReconnectAt >= 10000) {
            lastWifiReconnectAt = now;
            WiFi.reconnect();
        }

        if (!wifiSetupPortalActive && now - wifiDisconnectedAt >= 30000) {
            startWifiSetupPortal();
        }
    } else if (!isOnline) {
        isOnline = true;
        wifiDisconnectedAt = 0;
        lcdPrint("WIFI TERHUBUNG", "KIRIM DATA...");
        delay(500);
        flushOfflineQueue();
        sendHeartbeat();
        showReadyScreen();
    }

    // Heartbeat
    if (isOnline && now - lastHeartbeat > HEARTBEAT_INTERVAL) {
        sendHeartbeat();
    }

    // Sync periodik
    if (isOnline && needsSync && now - lastSync > SYNC_INTERVAL) {
        syncFromServer();
        flushOfflineQueue();
        showReadyScreen();
    }

    // ── BACA RFID ────────────────────────────────────────
    if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
        return;
    }

    // Format UID ke hex string (uppercase)
    String uid = "";
    for (byte i = 0; i < rfid.uid.size; i++) {
        if (rfid.uid.uidByte[i] < 0x10) uid += "0";
        uid += String(rfid.uid.uidByte[i], HEX);
    }
    uid.toUpperCase();

    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();

    // Debounce: abaikan scan yang sama dalam 3 detik
    if (uid == lastUID && millis() - lastScanBlock < 3000) {
        return;
    }
    lastUID       = uid;
    lastScanBlock = millis();

    String ts = getTimestamp();
    Serial.println("[SCAN] UID=" + uid + " ts=" + ts);

    if (isOnline) {
        processOnline(uid, ts);
    } else {
        processOffline(uid, ts);
    }
}
