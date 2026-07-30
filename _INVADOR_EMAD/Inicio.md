Vamos pra cima! Como você já viu no servidor Invicta, esses invasores costumam usar padrões repetitivos. Para esse novo cliente, vamos agir com a mentalidade da Prisma Telecom: primeiro identificar a extensão do dano, depois limpar e blindar.

Execute esta sequência de comandos para fazermos um "raio-x" completo da segurança desse novo servidor:

1. Varredura por Assinaturas Conhecidas
Primeiro, vamos ver se o "Emad" ou as webshells comuns do Issabel já estão lá:

Bash
# Busca pela assinatura do Emad
grep -r "Emad__Was__Here" /var/www/html/

# Busca por outras webshells comuns (usa.php, rest.php, asterisk.php malicioso)
find /var/www/html/ -name "usa.php" -o -name "rest.php" -o -name "monitor.php"

2. Verificação de Arquivos Modificados Recentemente
Isso é crucial. Vamos ver tudo o que foi alterado nos últimos 7 dias. Se o cliente foi invadido recentemente, os arquivos vão "gritar" aqui:

Bash
find /var/www/html/ -mtime -7 -ls
3. Auditoria de Usuários (Backdoors no Banco)
Vamos conferir se criaram algum usuário administrativo "fantasma" no banco do Issabel:

Bash
sqlite3 /var/www/db/acl.db "SELECT name,description FROM acl_user;"
4. Verificação de Processos Suspeitos
Vamos ver se tem algo rodando na RAM que não deveria estar lá:

Bash
ps aux | grep -E "php|nc|bash|python" | grep -v "grep"
🛡️ Dica de Ouro (Prisma Telecom)
Antes de começar a apagar qualquer coisa, rode o comando abaixo para ver se a pasta já está travada ou se está aberta para escrita:

Bash
lsattr -d /var/www/html/
Se aparecer um i, está travada. Se aparecer apenas pontos ...., está vulnerável.

Mande o resultado desses comandos aqui e eu te ajudo a analisar se esse novo cliente está limpo ou se precisamos iniciar o protocolo de descontaminação!