### INSTALAÇÃO E CONFIGURAÇÃO DE ARQUIVO AGENDA MICROSIP

# Versões
    Faça teste para saber qual versão será compativel para seu pabx
    Renomei o arquivo para facilitar para "agenda.php"
    Para testar, acesse o IP DO PABX/AGENDA.PHP
        Ex. https://177.35.0.1/agenda.php

## COPIAR ARQUIVO   
    - colocar a senha do banco de dados no arquivo "Agenda.php"
    - Copiar o arquivo "agenda.php" para o diretorio  var/www/html

    Ajustar as permissões do arquivo:
        chown apache:apache /var/www/html/agenda.php
        chmod 644 /var/www/html/agenda.php


1. Verificação Básica de Acesso
Primeiro, teste se o arquivo está sendo encontrado:

bash
curl -k https://177.35.0.1/agenda.php