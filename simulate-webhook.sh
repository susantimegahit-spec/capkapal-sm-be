#!/bin/bash
# ─────────────────────────────────────────────────────────────
# Simulasi Midtrans Webhook Notification ke localhost
# Usage: bash simulate-webhook.sh <order_id> <transaction_status>
#
# Contoh:
#   bash simulate-webhook.sh VA-GCK-20260527-00001-1779852402 settlement
#   bash simulate-webhook.sh VA-GCK-20260527-00002-1779852999 expire
# ─────────────────────────────────────────────────────────────

ORDER_ID="${1:?Usage: bash simulate-webhook.sh <order_id> <transaction_status>}"
TXN_STATUS="${2:-settlement}"     # default: settlement
GROSS_AMOUNT="${3:-58000.00}"     # default: 58000.00
STATUS_CODE="${4:-200}"           # 200 for settlement, 201 for pending

# Baca server key dari .env
SERVER_KEY=$(grep '^MIDTRANS_SERVER_KEY=' .env | cut -d'=' -f2)
if [ -z "$SERVER_KEY" ]; then
  echo "❌ MIDTRANS_SERVER_KEY tidak ditemukan di .env"
  exit 1
fi

# Generate signature_key: SHA-512(order_id + status_code + gross_amount + server_key)
SIGNATURE=$(echo -n "${ORDER_ID}${STATUS_CODE}${GROSS_AMOUNT}${SERVER_KEY}" | shasum -a 512 | awk '{print $1}')

TRANSACTION_ID=$(python3 -c "import uuid; print(uuid.uuid4())")
TXN_TIME=$(date +"%Y-%m-%d %H:%M:%S")

echo "📤 Sending Midtrans notification to localhost:8000"
echo "   Order ID:     $ORDER_ID"
echo "   Status:       $TXN_STATUS"
echo "   Amount:       $GROSS_AMOUNT"
echo "   Signature:    ${SIGNATURE:0:32}..."
echo ""

curl -s -X POST "http://localhost:8000/api/v1/webhooks/midtrans" \
  -H "Content-Type: application/json" \
  -d "{
    \"transaction_id\": \"${TRANSACTION_ID}\",
    \"order_id\": \"${ORDER_ID}\",
    \"transaction_status\": \"${TXN_STATUS}\",
    \"fraud_status\": \"accept\",
    \"status_code\": \"${STATUS_CODE}\",
    \"gross_amount\": \"${GROSS_AMOUNT}\",
    \"payment_type\": \"bank_transfer\",
    \"signature_key\": \"${SIGNATURE}\",
    \"merchant_id\": \"M894528288\",
    \"transaction_time\": \"${TXN_TIME}\",
    \"settlement_time\": \"${TXN_TIME}\",
    \"va_numbers\": [{\"bank\": \"bca\", \"va_number\": \"12345678901234\"}]
  }" | python3 -m json.tool

echo ""
echo "✅ Done. Check queue worker output for job processing."
