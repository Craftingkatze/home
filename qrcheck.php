<?php
if (isset($_GET['serial'])) {
    $serial = $_GET['serial'];
    if (strlen($serial) == 15) {
        // Beispiel für Seriennummer: VGTETE10B240175
        // VG = Vorname, TE = Fach, TE = Nachname, 10B = Klasse, 240175 = Datum

        // Vorname und Nachname
        $firstNameCode = substr($serial, 0, 2); // VG
        $firstName = ($firstNameCode == 'VG') ? 'Vincent Gawron' : $firstNameCode;

        // Schulfach
        $subjectCode = substr($serial, 2, 2); // TE
        
        // Fach-Map
        $subjectMap = [
            'MA' => 'Mathematik',
            'DE' => 'Deutsch',
            'EN' => 'Englisch',
            'SP' => 'Spanisch',
            'PH' => 'Physik',
            'BI' => 'Biologie',
            'CH' => 'Chemie',
            'GE' => 'Geschichte',
            'ER' => 'Erdkunde',
            'KU' => 'Kunst',
            'MU' => 'Musik',
            'NA' => 'Nicht Angegeben'
        ];
        $subject = $subjectMap[$subjectCode] ?? $subjectCode; // Standard: Abkürzung

        // Nachname (zweite TE)
        $teacherLastName = substr($serial, 4, 2); // TE

        // Klasse
        $class = substr($serial, 6, 3); // 10B

        // Datum
        $date = substr($serial, 9, 6); // 240175 -> 240175

        // Datum formatieren (DD.MM.YY)
        $day = substr($date, 0, 2);   // 24
        $month = substr($date, 2, 2); // 01
        $year = substr($date, 4, 2);  // 75
        $formattedDate = $day . '.' . $month . '.' . $year; // 24.01.75

        $message = "<h2>Seriennummer Analyse</h2>";
        $message .= "<p><strong>Vorname:</strong> " . $firstName . "</p>";
        $message .= "<p><strong>Schulfach:</strong> " . $subject . "</p>";
        $message .= "<p><strong>Lehrer:</strong> " . $teacherLastName . "</p>";
        $message .= "<p><strong>Klasse:</strong> " . $class . "</p>";
        $message .= "<p><strong>Datum:</strong> " . $formattedDate . "</p>";

        // Verlustnachricht nur anzeigen, wenn der Name "Vincent Gawron" ist
       
    } else {
        $message = "<p class='error'>Fehler: Die Seriennummer hat nicht die richtige Länge.</p>";
    }
} else {
    $message = "<p class='error'>Gib eine Seriennummer in der URL ein, z.B. ?serial=VGTETE10B240175</p>";
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seriennummer Analyse</title>
    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #e0f7fa; /* Helles türkis für den Hintergrund */
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
        color: #333;
    }

    .container {
        text-align: center;
        background-color: #ffffff;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        max-width: 600px;
        width: 100%;
        font-size: 18px;
        margin-bottom: 20px;
    }

    h2 {
        font-size: 28px;
        color: #00796b;
    }

    p {
        margin: 10px 0;
        color: #555;
    }

    .error {
        color: #e53935; /* Rot für Fehlermeldung */
        font-weight: bold;
    }

    .loss-message {
        margin-top: 20px;
        padding: 20px;
        border: 2px solid #e53935;
        border-radius: 10px;
        background-color: #ffebee; /* Helles Rot */
    }

    .contact-link {
        text-decoration: none;
        background-color: #e53935;
        color: #fff;
        padding: 10px 20px;
        border-radius: 5px;
        font-weight: bold;
        margin-top: 20px;
        display: inline-block;
        transition: background-color 0.3s;
    }

    .contact-link:hover {
        background-color: #b71c1c;
    }

    .info {
        margin-top: 20px;
    }

    .info p {
        font-size: 18px;
        color: #00796b;
    }

    .back-link {
        text-decoration: none;
        background-color: #00796b;
        color: #fff;
        padding: 10px 20px;
        border-radius: 5px;
        font-weight: bold;
        margin-top: 20px;
        display: inline-block;
        transition: background-color 0.3s;
    }

    .back-link:hover {
        background-color: #004d40;
    }

    #contactFormContainer {
        margin-top: 20px;
        background-color: #fafafa;
        padding: 20px;
        border: 1px solid #ccc;
        border-radius: 10px;
        display: none;
        box-sizing: border-box;
    }

    input, textarea, button {
        width: calc(100% - 22px);
        padding: 10px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }

    button {
        text-decoration: none;
        background-color: #00796b;
        color: #fff;
        padding: 10px 20px;
        border-radius: 5px;
        font-weight: bold;
        font-size: 20px;
        margin-top: 20px;
        display: inline-block;
        transition: background-color 0.3s;
    }

    button:hover {
        background-color: #004d40;
    }

    textarea {
        resize: vertical;
    }

    #showFormBtn {
        background-color: #e53935;
        color: white;
        font-size: 18px;
        padding: 10px 20px;
        cursor: pointer;
        border: none;
        border-radius: 5px;
        margin-bottom: 20px;
        margin-top: 15px;
    }

    #showFormBtn:hover {
        background-color:rgb(168, 42, 40);
    }

    h3 {
        font-size: 24px;
        color: #e53935;
    }

    .loss-message p {
        color: #d32f2f;
    }

    /* Responsive Styles */
    @media (max-width: 768px) {
        h2 {
            font-size: 24px;
        }

        .container {
            padding: 15px;
            font-size: 16px;
        }

        input, textarea, button {
            width: 100%;
            font-size: 16px;
        }

        button {
            font-size: 18px;
        }

        #showFormBtn {
            font-size: 16px;
        }
    }

    @media (max-width: 480px) {
        h2 {
            font-size: 20px;
        }

        .container {
            padding: 10px;
            font-size: 14px;
        }

        input, textarea, button {
            font-size: 14px;
        }

        #showFormBtn {
            font-size: 14px;
        }
    }
    </style>

</head>
<body>
    <div class="container">
        <div class="message">
            <?php echo $message; ?>
        </div>

        
  </div>
<br>
        <div class="container">


        <?php if (isset($firstName) && $firstName === 'Vincent Gawron'): ?>
    <div class="loss-message">
        <h3 style="color: #e53935;">Verlustmeldung</h3>
        <p style="color: #d32f2f;">Wenn Sie dieses Objekt gefunden haben und es dem Besitzer zurückgeben möchten, kontaktieren Sie mich bitte.</p>

        <button id="showFormBtn" onclick="toggleForm()">Kontakt</button>

        <div id="contactFormContainer" style="display: none;">
            <form id="contactForm" onsubmit="generateEmail(event)">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required><br><br>
                
                <label for="phone">Telefonnummer:</label>
                <input type="tel" id="phone" name="phone" required><br><br>
                
                <label for="location">Fundort:</label>
                <input type="text" id="location" name="location" required><br><br>
                
                <label for="message">Nachricht:</label>
                <textarea id="message" name="message" rows="4" required></textarea><br><br>

                <button type="submit">Senden</button>
            </form>
        </div>
    </div>

    <script>
    // Funktion zum Anzeigen des Formulars
    function toggleForm() {
        const formContainer = document.getElementById("contactFormContainer");
        const button = document.getElementById("showFormBtn");

        if (formContainer.style.display === "none") {
            formContainer.style.display = "block";
            button.innerHTML = "Formular schließen"; // Buttontext ändern
        } else {
            formContainer.style.display = "none";
            button.innerHTML = "Kontakt"; // Buttontext zurücksetzen
        }
    }

    // Funktion zum Generieren der E-Mail
    function generateEmail(event) {
        event.preventDefault();  // Verhindert, dass das Formular normal abgeschickt wird
        
        // Holt die Eingabewerte
        const name = document.getElementById("name").value;
        const phone = document.getElementById("phone").value;
        const location = document.getElementById("location").value;
        const message = document.getElementById("message").value;
        
        // Holt die Seriennummer aus der URL
        const urlParams = new URLSearchParams(window.location.search);
        const serial = urlParams.get('serial');  // Extrahiert die Seriennummer aus der URL

        if (!serial) {
            alert("Seriennummer fehlt im Link!");
            return;
        }

        // Erstellt den Betreff und Text der E-Mail (mit echten Zeilenumbrüchen)
        const subject = `Verlustmeldung mit Seriennummer ${serial}`;
        const body = `Hallo,\n\nMein Name ist ${name}.\nIch habe das Objekt mit der Seriennummer ${serial} gefunden.\nHier sind die Details:\n- Telefonnummer: ${phone}\n- Fundort: ${location}\n\nNachricht: ${message}\n\nHier ist der Link zur Seriennummer: https://craftingkatze.de/qrcheck.php?serial=${serial}\n\nGruß`;

        // Öffnet den E-Mail-Link
        window.location.href = `mailto:craftingkatze@gmail.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    }
</script>

<?php endif; ?>
</div>

    
</body>
</html>
