# Extensão Google Chrome - IPBX Prisma Telecom

### BR - Descrição: ###
É uma extensão de discagem rapida no navegador Google Chrome.

### Instruções de instalação ###
  
- Baixe o arquivo PHP do github chamado call.php
  - Adicionar o arquivo call.php na pasta /var/www/html/

- Alterar o tipo de extensão utilizada no arquivo call.php, podendo ser SIP ou PJSIP.
  - //$strChannel = "SIP/".$ext; #Alterar conforme extensão de ramal utilizada.
  - $strChannel = "PJSIP/".$ext; #Alterar conforme extensão de ramal utilizada.


## Teste da extenção
  - Adicionar o arquivo call.php na pasta /var/www/html/
  
  - https://192.168.0.251/call.php?exten=201&number=200
  - https://ipdoseupabx/call.phh?exten=RAMALORIGEM&number=RAMALDESTINO

Observe que seu servidor FreePBX deve ter o PHP instalado.

Se você instalou a distribuição nativa do FreePBX, provavelmente está no CentOS e precisa executar este comando:
```
sudo yum install php
```
 
Se você estiver no Ubuntu, o comando seria
```
sudo apt-get install php5
```

## Outras Informações ##

  - Video PT-BR instrução Jolt-Select-Dial-PBX:   https://www.youtube.com/watch?v=UNLYn6ADl2Y

### Extensão baseada no: ###

  - Jolt-Select-Dial-PBX
    - Github:  https://github.com/Jolt1/Jolt-Select-Dial-PBX/blob/master/README.md

    - Instructions on how to configure Chrome Extension https://www.youtube.com/watch?v=NgU84smstGg

    - Instructions on how to configure PHP File https://www.youtube.com/watch?v=_qhy47BBBUE
  


## Como eu posso ajudar? ##
Ajude-nos a entregar um conteúdo de qualidade. Toda ajuda é bem vinda.

## Autor ##
Autor: Leandro Saltori - Prisma Telecom
