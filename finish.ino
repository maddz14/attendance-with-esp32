#include <Adafruit_Fingerprint.h>
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <Wire.h>
#include <RTClib.h>
#include <LiquidCrystal_I2C.h>

// WiFi credentials
const char* ssid = "RINA";     // Ganti dengan SSID WiFi
const char* password = "Puskesmas";  // Ganti dengan password WiFi

// Server URL
const char* serverUrl = "http://localhost/api/attendance";

// Pin definitions
#define BUZZER_PIN 14  // D5 (GPIO14)
#define RX_PIN 12      // D6 (GPIO12)
#define TX_PIN 13      // D7 (GPIO13)

SoftwareSerial mySerial(RX_PIN, TX_PIN);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);

RTC_DS3231 rtc;
LiquidCrystal_I2C lcd(0x27, 16, 2); // Sesuaikan alamat I2C LCD

String offlineBuffer[50];
int bufferIndex = 0;

String getDeviceId(int fingerId) {
  if (fingerId >= 1 && fingerId <= 50) return "CLASS_X";
  else if (fingerId >= 51 && fingerId <= 100) return "CLASS_XI";
  else return "TIDAK_DIKENAL";
}

void printCentered(String text, int row) {
  lcd.setCursor(0, row);
  lcd.print("                ");
  lcd.setCursor((16 - text.length()) / 2, row);
  lcd.print(text);
}

void setup() {
  Serial.begin(115200);
  finger.begin(57600);
  pinMode(BUZZER_PIN, OUTPUT);

  // Inisialisasi LCD
  Wire.begin();
  lcd.init();
  lcd.backlight();
  printCentered("Memulai...", 0);

  // Inisialisasi RTC
  if (!rtc.begin()) {
    printCentered("RTC Gagal!", 1);
    while(1);
  }

  // ATUR WAKTU MANUAL DI SINI (Format: Tahun, Bulan, Tanggal, Jam, Menit, Detik)
  // Contoh untuk 15 Juli 2024 jam 19:55:00
  if (rtc.lostPower() || rtc.now().year() < 2024) {
    rtc.adjust(DateTime(2025, 4, 17, 19, 55, 0)); // ✏️ EDIT TANGGAL & WAKTU DI SINI
    printCentered("Waktu Diatur!", 1);
  }

  // Koneksi WiFi
  WiFi.begin(ssid, password);
  int wifiCounter = 0;
  while (WiFi.status() != WL_CONNECTED && wifiCounter < 15) {
    delay(1000);
    printCentered("WiFi: " + String(++wifiCounter), 1);
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    printCentered("WiFi Terhubung", 1);
  } else {
    printCentered("WiFi Gagal", 1);
  }

  // Verifikasi sensor
  if (finger.verifyPassword()) {
    printCentered("Sensor Siap", 0);
    delay(1000);
  } else {
    printCentered("Sensor Error!", 0);
    while(1);
  }
}

void loop() {
  // Tampilkan waktu real-time
  DateTime now = rtc.now();
  char timeStr[9];
  sprintf(timeStr, "%02d:%02d:%02d", now.hour(), now.minute(), now.second());
  printCentered("Tempelkan Jari", 0);
  printCentered(timeStr, 1);

  int fingerId = getFingerprintID();
  
  if (fingerId > 0) {
    // Bunyikan buzzer 1x
    digitalWrite(BUZZER_PIN, HIGH);
    delay(200);
    digitalWrite(BUZZER_PIN, LOW);
    
    String deviceId = getDeviceId(fingerId);
    String timestamp = now.timestamp().substring(0, 19); // Format: "YYYY-MM-DDTHH:MM:SS"
    
    String payload = "{\"device_id\":\"" + deviceId + 
                    "\",\"finger_id\":" + String(fingerId) + 
                    ",\"timestamp\":\"" + timestamp + "\"}";

    if (WiFi.status() == WL_CONNECTED) {
      sendToServer(payload);
      printCentered("Data Terkirim!", 0);
    } else {
      offlineBuffer[bufferIndex] = payload;
      bufferIndex = (bufferIndex + 1) % 50;
      printCentered("Disimpan Offline", 0);
    }
    delay(1000);
  } else if (fingerId == -1) {
    // Bunyikan buzzer 2x
    for(int i=0; i<2; i++) {
      digitalWrite(BUZZER_PIN, HIGH);
      delay(200);
      digitalWrite(BUZZER_PIN, LOW);
      delay(200);
    }
    printCentered("Tidak Dikenali", 0);
    delay(1000);
  }
}

int getFingerprintID() {
  if (finger.getImage() != FINGERPRINT_OK) return 0;
  if (finger.image2Tz() != FINGERPRINT_OK) return 0;
  if (finger.fingerFastSearch() != FINGERPRINT_OK) return -1;
  return finger.fingerID;
}

void sendToServer(String payload) {
  WiFiClient client;
  HTTPClient http;
  
  http.begin(client, serverUrl);
  http.addHeader("Content-Type", "application/json");
  
  int httpCode = http.POST(payload);
  if (httpCode == HTTP_CODE_OK) {
    Serial.println("Server OK: " + payload);
  } else {
    Serial.println("Error: " + String(httpCode));
  }
  http.end();
}