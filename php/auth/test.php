<?php
try {
    $host = "bzlwnzdfwf8n1tct7ebf-mysql.services.clever-cloud.com";
    $db_name = "bzlwnzdfwf8n1tct7ebf";
    $username = "uiewshfkax9viaaw";
    $password = "ecxBIcUMIBgaN3SX0h6X";

    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexión exitosa a la base de datos";
    
    // Probar una consulta simple
    $stmt = $conn->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "<br>✅ Consulta de prueba: " . $result['test'];
    
} catch(PDOException $exception) {
    echo "❌ Error de conexión: " . $exception->getMessage() . "<br>";
    echo "Código de error: " . $exception->getCode() . "<br>";
    echo "Información adicional:<br>";
    echo "- Host: $host<br>";
    echo "- Base de datos: $db_name<br>";
    echo "- Usuario: $username<br>";
}
?>