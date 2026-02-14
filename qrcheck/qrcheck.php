<?php
if (isset($_GET['serial'])) {
    $serial = htmlspecialchars($_GET['serial']);
    echo "<h1>Seriennummer: $serial</h1>";
} else {
    echo "<h1>Error 404 - Not found</h1>";
    echo "<p>Die angegebene Seite konnte nicht gefunden werden.</p>";
}
?>
