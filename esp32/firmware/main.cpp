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
#include <ArduinoJson.h>
#include <LittleFS.h>
#include <SPI.h>
#include <MFRC522.h>
#include <LiquidCrystal_I2C.h>
#include <time.h>

// ── Konfigurasi WiFi & Server ─────────────────────────────
#if __has_include("secrets.h")
#include "secrets.h"
#else
#define WIFI_SSID ""
#define WIFI_PASSWORD ""
#define SERVER_URL "http://127.0.0.1:8000"
#define DEVICE_API_KEY "secret-api-key-123"
#endif

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

// ── NTP ───────────────────────────────────────────────────
#define NTP_SERVER    "pool.ntp.org"
#define TZ_OFFSET     25200         // UTC+7 (7 * 3600)

// ── Objects ───────────────────────────────────────────────
MFRC522         rfid(SS_PIN, RST_PIN);
LiquidCrystal_I2C lcd(0x27, LCD_COLS, LCD_ROWS);

// ── State ─────────────────────────────────────────────────
bool isOnline               = false;
bool needsSync              = true;
unsigned long lastHeartbeat = 0;
unsigned long lastSync      = 0;
unsigned long lastScanBlock = 0;     // Anti double-tap (debounce 3 detik)
String lastUID              = "";

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

void lcdPrint(String line1, String line2 = "") {
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print(line1.substring(0, LCD_COLS));
    if (line2 != "") {
        lcd.setCursor(0, 1);
        lcd.print(line2.substring(0, LCD_COLS));
    }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// WIFI
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

bool connectWifi() {
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    lcdPrint("Menghubungkan...", "WiFi: " + String(WIFI_SSID));
    int tries = 0;
    while (WiFi.status() != WL_CONNECTED && tries < 20) {
        delay(500); tries++;
    }
    isOnline = (WiFi.status() == WL_CONNECTED);
    if (isOnline) {
        configTime(TZ_OFFSET, 0, NTP_SERVER);
        lcdPrint("WiFi Terhubung!", WiFi.localIP().toString());
        delay(1500);
    } else {
        lcdPrint("WiFi Gagal!", "Mode Offline");
        delay(1500);
    }
    return isOnline;
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
    lcdPrint("Sinkronisasi...", "Harap Tunggu");

    String resp = httpGet("/api/sync");
    if (resp == "") {
        Serial.println("[SYNC] Gagal: server tidak respons.");
        return false;
    }

    DynamicJsonDocument doc(65536);
    if (deserializeJson(doc, resp) != DeserializationError::Ok) return false;
    if (!doc["success"].as<bool>()) return false;

    // Simpan ke LittleFS
    saveCacheToFS(resp);

    // Reload dari file (agar konsisten)
    loadCacheFromFS();

    lastSync = millis();
    needsSync = false;

    lcdPrint("Sync Berhasil!", String(cacheCount) + " Siswa");
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
            lcdPrint(s->name, "[OFFLINE] " + status);
            buzz(s ? 2 : 4, 100);
        } else {
            lcdPrint("Kartu Asing!", "[Disimpan Lokal]");
            buzz(3, 150);
        }
        delay(2500);
        return;
    }

    DynamicJsonDocument doc(512);
    if (deserializeJson(doc, resp) != DeserializationError::Ok) return;

    String code    = doc["code"].as<String>();
    String message = doc["message"].as<String>();
    String status  = doc["status"].as<String>();
    String name    = doc["student"].as<String>();

    Serial.println("[TAP] Code=" + code + " Name=" + name + " Status=" + status);

    if (code == "SUCCESS") {
        String line2 = (status == "HADIR") ? "Hadir ✓" : "TERLAMBAT !";
        lcdPrint(name, line2);
        buzz(status == "HADIR" ? 1 : 2, 120);
    } else if (code == "DUPLICATE") {
        lcdPrint(name, "Sudah Absen: " + status);
        buzz(1, 50);
    } else if (code == "UNKNOWN_CARD") {
        lcdPrint("Kartu Tidak", "Dikenal!");
        buzz(3, 150);
    } else if (code == "HOLIDAY") {
        lcdPrint("Hari Libur!", message.substring(0, 16));
        buzz(2, 100);
    } else if (code == "TOO_EARLY") {
        lcdPrint("Terlalu Awal!", "Belum Waktunya");
        buzz(1, 80);
    } else if (code == "TOO_LATE") {
        lcdPrint("Waktu Absen", "Sudah Ditutup!");
        buzz(3, 100);
    } else {
        lcdPrint("Gagal!", message.substring(0, 16));
        buzz(2, 200);
    }

    delay(2500);
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PROSES ABSENSI OFFLINE (dari cache LittleFS)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

void processOffline(String uid, String ts) {
    Student* s = findStudentByUID(uid);

    if (!s) {
        lcdPrint("Kartu Asing!", "Mode Offline");
        buzz(4, 150);
        delay(2500);
        return;
    }

    if (!isInAbsenceWindow()) {
        lcdPrint("Waktu Absen", "Tidak Valid!");
        buzz(2, 150);
        delay(2000);
        return;
    }

    String ikey   = makeIdempotencyKey(uid, ts);
    String status = isLate() ? "TERLAMBAT" : "HADIR";

    // Simpan ke antrean offline untuk dikirim nanti
    if (queueCount < MAX_QUEUE_SIZE) {
        offlineQueue[queueCount++] = {uid, ts, ikey};
        saveQueueToFS();
    }

    lcdPrint(s->name, "[OFFLINE] " + status);
    buzz(status == "HADIR" ? 1 : 2, 120);
    delay(2500);
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

    if (!WIFI_SSID[0] || !DEVICE_API_KEY[0] || !SERVER_URL[0]) {
        Serial.println("Konfigurasi belum ada. Salin secrets.example.h menjadi secrets.h lalu isi nilainya.");
        lcd.init();
        lcd.backlight();
        lcdPrint("Konfigurasi", "secrets.h kosong");
        while (1) delay(1000);
    }

    // Init LCD
    lcd.init();
    lcd.backlight();
    lcdPrint("SMP RFID Absensi", "Inisialisasi...");

    // Init SPI + RFID
    SPI.begin();
    rfid.PCD_Init();
    delay(500);

    // Init LittleFS
    if (!LittleFS.begin(true)) {
        Serial.println("LittleFS Mount Failed!");
        lcdPrint("ERROR!", "LittleFS Gagal");
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

    lcdPrint("Siap Absensi!", isOnline ? "Mode: Online" : "Mode: Offline");
    delay(1000);
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// MAIN LOOP
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

void loop() {
    unsigned long now = millis();

    // Cek koneksi WiFi & reconnect otomatis
    if (WiFi.status() != WL_CONNECTED) {
        if (isOnline) {
            isOnline = false;
            lcdPrint("Koneksi Putus!", "Mode: Offline");
            delay(1000);
        }
    } else if (!isOnline) {
        isOnline = true;
        lcdPrint("Terhubung!", "Mode: Online");
        delay(500);
        flushOfflineQueue();
        sendHeartbeat();
    }

    // Heartbeat
    if (isOnline && now - lastHeartbeat > HEARTBEAT_INTERVAL) {
        sendHeartbeat();
    }

    // Sync periodik
    if (isOnline && needsSync && now - lastSync > SYNC_INTERVAL) {
        syncFromServer();
        flushOfflineQueue();
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
