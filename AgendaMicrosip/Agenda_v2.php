<?php
header('Content-Type: application/json; charset=utf-8');

// Conexão com o banco de dados MySQL
$conn = new mysqli("localhost", "root", "[SUASENHAAQUI]", "asterisk");
if ($conn->connect_error) {
    die(json_encode(array("error" => "Erro na conexão: " . $conn->connect_error)));
}

// Array para todos os itens
$items = array();

// 1. CONSULTA DE PRESENÇA DOS RAMAIS (CORRIGIDA)
$presence = array();
$result_presence = $conn->query("
    SELECT name as extension, 
           CASE 
               WHEN status = 'OK' THEN 1 
               ELSE 0 
           END as online_status
    FROM sip_peers
    WHERE name REGEXP '^[0-9]{2,4}$'
");

if ($result_presence) {
    while ($row = $result_presence->fetch_assoc()) {
        $presence[$row['extension']] = $row['online_status'];
    }
}

// 2. RAMAIS INTERNOS (COM PRESENÇA VISÍVEL)
$result_ramais = $conn->query("
    SELECT extension AS number, name 
    FROM users 
    WHERE extension REGEXP '^[0-9]{2,4}$'
    ORDER BY name ASC
");

if ($result_ramais && $result_ramais->num_rows > 0) {
    while ($row = $result_ramais->fetch_assoc()) {
        // Separa primeiro e último nome
        $name_parts = explode(' ', $row['name'], 2);
        $firstname = isset($name_parts[0]) ? $name_parts[0] : '';
        $lastname = isset($name_parts[1]) ? $name_parts[1] : '';
        
        // Status de presença (1 = online, 0 = offline)
        $status = isset($presence[$row['number']]) ? $presence[$row['number']] : 1;
        
        $items[] = array(
            "number"    => $row['number'],
            "name"      => $row['name'] ? $row['name'] : "Ramal ".$row['number'],
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
            "presence"  => $status, // Agora mostra corretamente online/offline
            "starred"   => 1,
            "info"      => ($status == 1) ? "Ramal online" : "Ramal offline"
        );
    }
}

// 3. CONTATOS EXTERNOS (SQLite)
$sqlite_path = '/var/www/db/address_book.db';
if (file_exists($sqlite_path)) {
    try {
        $sqlite = new SQLite3($sqlite_path);
        
        $result = $sqlite->query("
            SELECT name, last_name, telefono AS number, cell_phone AS mobile, 
                   email, address, city, province AS state, notes AS comment,
                   company, company_contact, contact_rol, department
            FROM contact 
            WHERE directory = 'external' AND telefono != ''
            ORDER BY name ASC
        ");
        
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $comment = "Empresa: ".$row['company']."\n";
                $comment .= "Contato: ".$row['company_contact']."\n";
                $comment .= "Cargo: ".$row['contact_rol']."\n";
                $comment .= "Departamento: ".$row['department'];
                
                if (!empty($row['comment'])) {
                    $comment = $row['comment']."\n".$comment;
                }
                
                $items[] = array(
                    "number"    => $row['number'],
                    "name"      => trim($row['name'].' '.$row['last_name']),
                    "firstname" => $row['name'],
                    "lastname"  => $row['last_name'],
                    "phone"     => $row['number'],
                    "mobile"    => $row['mobile'],
                    "email"     => $row['email'],
                    "address"   => $row['address'],
                    "city"      => $row['city'],
                    "state"     => $row['state'],
                    "zip"       => "",
                    "comment"   => $comment,
                    "presence"  => 0, // Contatos externos sempre sem presença
                    "starred"   => 0,
                    "info"      => "Contato externo"
                );
            }
        }
    } catch (Exception $e) {
        error_log("Erro SQLite: ".$e->getMessage());
    }
}

// 4. SAÍDA FINAL
echo json_encode(array(
    "refresh" => 30,
    "items" => $items
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Fechar conexões
$conn->close();
if (isset($sqlite)) $sqlite->close();
?>