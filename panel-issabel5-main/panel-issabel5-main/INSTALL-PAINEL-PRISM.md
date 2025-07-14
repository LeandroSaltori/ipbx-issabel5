#### INSTALAÇÃO PAINEL IPBX V5

## Passo 1 
    Enviar a pasta "control_panel" para o diretorio: /var/www/html/modules
          
## Passo 2 
    sqlite3 /var/www/db/acl.db
    INSERT INTO acl_resource (name, description) VALUES ('control_panel', 'Painel IPbx');

    Para Sair do sqlite, CTRL + D

## Passo 3
    sqlite3 /var/www/db/menu.db
    INSERT INTO menu (id, IdParent, Link, Name, Type, order_no) VALUES ('control_panel', 'pbxconfig', '', 'Painel IPbx', 'module', 8);

    Para Sair do sqlite, CTRL + D


Paso 3- en Sistema , ir a permisos de grupo "administrador" ingresar a "pbxconfig" y asignar permisos para "control_panel"
