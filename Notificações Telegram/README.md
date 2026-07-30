Aqui está o guia passo a passo, no padrão Prisma Telecom, para que seu técnico consiga replicar o monitoramento em qualquer servidor Issabel de forma rápida e organizada.

🚀 Guia de Implantação: Monitoramento de Usuários Issabel (via Telegram)
Este procedimento configura um alerta automático que notifica o grupo da Prisma sempre que um novo usuário for criado na interface web do Issabel.

1. Preparação do Arquivo
Acesse o terminal do Issabel via SSH e crie o arquivo do script:

yum install nano

nano /usr/local/bin/monitor_issabel_users.sh


2. Conteúdo do Script
Copie o código abaixo e cole dentro do editor. Atenção: Se for um cliente novo, altere apenas o nome na variável CLIENTE.

Bash
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
Pressione Ctrl+O, Enter para salvar e Ctrl+X para sair.

3. Permissões de Execução
Torne o script executável pelo sistema:

 chmod +x /usr/local/bin/monitor_issabel_users.sh
 
4. Agendamento Automático (Crontab)
Configure o Linux para rodar o script sozinho a cada 1 minuto:

Bash
(crontab -l 2>/dev/null; echo "* * * * * /usr/local/bin/monitor_issabel_users.sh") | crontab -

5. Validação do Técnico
Para garantir que está funcionando, o técnico deve seguir estes passos:

Limpar caches antigos (se houver): rm -f /tmp/last_user*

Primeira execução (Calibragem): 
Rode o script uma vez manualmente: 
/usr/local/bin/monitor_issabel_users.sh. 
(Nada deve acontecer no Telegram, ele apenas lerá os usuários atuais).

Teste Real: Vá na web do Issabel, crie um usuário de teste e aguarde até 1 minuto. O alerta deve chegar no Telegram.

Dica Extra: Se o técnico precisar desativar o monitoramento de um cliente, basta rodar crontab -e e colocar um # na frente da linha do script ou remover a linha.