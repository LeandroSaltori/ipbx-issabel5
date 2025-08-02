<?php
header('Content-Type: application/json; charset=utf-8');

// 1. Conexão com MySQL (Ramais internos)
$mysql = new mysqli("localhost", "root", "[SUASENHAAQUI]", "asterisk");
if ($mysql->connect_error) {
    die(json_encode(["error" => "Erro MySQL: ".$mysql->connect_error]));
}

// 2. Busca de ramais internos
$items = [];
$result = $mysql->query("
    SELECT extension AS number, name 
    FROM users 
    WHERE extension REGEXP '^[0-9]{2,4}$'
    ORDER BY name ASC
");

while ($row = $result->fetch_assoc()) {
    // Separa primeiro e último nome
    $name_parts = explode(' ', $row['name'], 2);
    $firstname = $name_parts[0] ?? '';
    $lastname = $name_parts[1] ?? '';
    
    $items[] = [
        "number"    => $row['number'],
        "name"      => $row['name'] ?: "Ramal ".$row['number'],
        "firstname" => $firstname,
        "lastname"  => $lastname,
        "phone"     => $row['number'],
        "mobile"    => "",
        "email"     => "",
        "address"   => "",
        "city"      => "",
        "state"     => "",
        "zip"       => "",
        "comment"   => "Ramal interno",
        "presence"  => 1,
        "starred"   => 1,
        "info"      => "Ramal do sistema"
    ];
}

// 3. Conexão com SQLite (Contatos externos)
$sqlite_path = '/var/www/db/address_book.db';
if (file_exists($sqlite_path)) {
    try {
        $sqlite = new SQLite3($sqlite_path);
        
        // Consulta adaptada para pegar todos os campos disponíveis
        $result = $sqlite->query("
            SELECT name, last_name, telefono AS number, cell_phone AS mobile, 
                   email, address, city, province AS state, notes AS comment,
                   company, company_contact, contact_rol, department
            FROM contact 
            WHERE directory = 'external' AND telefono != ''
            ORDER BY name ASC
        ");
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = [
                "number"      => $row['number'],
                "name"       => trim($row['name'].' '.$row['last_name']),
                "firstname"  => $row['name'],
                "lastname"   => $row['last_name'],
                "phone"      => $row['number'],
                "mobile"     => $row['mobile'],
                "email"      => $row['email'],
                "address"    => $row['address'],
                "city"       => $row['city'],
                "state"      => $row['state'],
                "zip"        => "", // Não existe no schema
                "comment"    => $row['comment']."\nEmpresa: ".$row['company']."\nContato: ".$row['company_contact']."\nCargo: ".$row['contact_rol']."\nDepartamento: ".$row['department'],
                "presence"   => 0,
                "starred"    => 0,
                "info"       => "Contato externo"
            ];
        }
    } catch (Exception $e) {
        file_put_contents('/tmp/agenda_errors.log', date('[Y-m-d H:i:s] ').$e->getMessage().PHP_EOL, FILE_APPEND);
    }
}

// 4. Saída JSON formatada para MicroSIP
echo json_encode([
    "refresh" => 1, // 5 minutos
    "items"   => $items
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Fechar conexões
$mysql->close();
if (isset($sqlite)) $sqlite->close();
?>
