
#!/bin/bash

# --- CONFIGURAÇÃO PRISMA TELECOM ---
CLIENTE="IPBX - PRISMA TELECOM"
TOKEN="7558673015:AAEk7FbRtOXCB2xiiQ1fRT0Hwi-KEjf4JlI"
CHAT_ID="-1003562264947"

DB_PATH="/var/www/db/acl.db"
SNAPSHOT_FILE="/tmp/issabel_users_snapshot.txt"

# 1. Captura lista atual do banco
CURRENT_USERS=$(sqlite3 $DB_PATH "SELECT name || '|' || description FROM acl_user;")

# 2. Inicialização (Primeira rodada)
if [ ! -f "$SNAPSHOT_FILE" ]; then
    echo "$CURRENT_USERS" > "$SNAPSHOT_FILE"
    exit 0
fi

# 3. Comparação de Snapshot
OLD_USERS=$(cat "$SNAPSHOT_FILE")

echo "$CURRENT_USERS" | while read -r LINE; do
    if ! echo "$OLD_USERS" | grep -qxF "$LINE"; then
        USER_NAME=$(echo "$LINE" | cut -d'|' -f1)
        USER_DESC=$(echo "$LINE" | cut -d'|' -f2)

        # Mensagem com Emojis via Hex Code para evitar caracteres especiais no Linux
        MSG="%E2%9A%A0 *ALERTA: NOVO USUARIO IPBX - PRISMA*%0A%0A"
        MSG="${MSG}%F0%9F%93%8C Cliente: ${CLIENTE}%0A"
        MSG="${MSG}%F0%9F%91%A4 Login: ${USER_NAME}%0A"
        MSG="${MSG}%F0%9F%93%9D Descricao user: ${USER_DESC}%0A"
        MSG="${MSG}%F0%9F%93%85 Data: $(date '+%d/%m/%Y %H:%M:%S')"

        curl -s -X POST "https://api.telegram.org/bot$TOKEN/sendMessage" \
            -d "chat_id=$CHAT_ID" \
            -d "text=$MSG" \
            -d "parse_mode=Markdown" > /dev/null
    fi
done

# 4. Atualiza base de comparação
echo "$CURRENT_USERS" > "$SNAPSHOT_FILE"