/*
 * Client SentinelHub pour ESP-IDF (v5/v6), à intégrer comme composant SentinelOS.
 *
 * Le token est lu depuis la partition NVS (chiffrée avec Flash Encryption),
 * jamais compilé en dur dans le firmware.
 */
#include <stdio.h>
#include "esp_http_client.h"
#include "esp_crt_bundle.h"
#include "esp_log.h"
#include "nvs.h"

#define SENTINELHUB_URL "https://hub.example.lan/api/v1/telemetry"
#define FW_VERSION "1.4.2"

static const char *TAG = "sentinelhub";

static esp_err_t load_token(char *out, size_t len)
{
    nvs_handle_t nvs;
    ESP_ERROR_CHECK(nvs_open("sentinel", NVS_READONLY, &nvs));
    esp_err_t err = nvs_get_str(nvs, "hub_token", out, &len);
    nvs_close(nvs);
    return err;
}

esp_err_t sentinelhub_send_telemetry(float temperature, float humidity, int rssi, uint32_t free_heap)
{
    char token[80];
    if (load_token(token, sizeof token) != ESP_OK) {
        ESP_LOGE(TAG, "token absent de la NVS");
        return ESP_ERR_NOT_FOUND;
    }

    char auth[96];
    snprintf(auth, sizeof auth, "Bearer %s", token);

    char body[192];
    int n = snprintf(body, sizeof body,
        "{\"metrics\":{\"temperature\":%.1f,\"humidity\":%.1f,\"rssi\":%d,\"heap\":%lu},\"firmware\":\"%s\"}",
        temperature, humidity, rssi, (unsigned long)(free_heap / 1024), FW_VERSION);

    esp_http_client_config_t cfg = {
        .url = SENTINELHUB_URL,
        .method = HTTP_METHOD_POST,
        .timeout_ms = 5000,
        .crt_bundle_attach = esp_crt_bundle_attach,  // vérification TLS du serveur
    };
    esp_http_client_handle_t client = esp_http_client_init(&cfg);
    esp_http_client_set_header(client, "Authorization", auth);
    esp_http_client_set_header(client, "Content-Type", "application/json");
    esp_http_client_set_post_field(client, body, n);

    esp_err_t err = esp_http_client_perform(client);
    int status = esp_http_client_get_status_code(client);
    if (err == ESP_OK && status == 202) {
        ESP_LOGI(TAG, "télémétrie acceptée");
    } else {
        ESP_LOGW(TAG, "échec d'envoi: %s (HTTP %d)", esp_err_to_name(err), status);
    }

    esp_http_client_cleanup(client);
    return err;
}
