
<?php
// ==============================
// CONFIGURATION
// ==============================
$tap_secret_key = "sk_test_xxx"; // Replace with your Tap Secret Key
$redirect_url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?completed=1";

// ==============================
// HANDLE AJAX PAYMENT REQUEST
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    header('Content-Type: application/json');

    $token = $_POST['token'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone_code = $_POST['phone_code'];
    $phone_number = $_POST['phone_number'];

    // ==============================
    // CREATE CHARGE VIA TAP API
    // ==============================
    $postData = [
        "amount" => (float)$amount,
        "currency" => $currency,
        "threeDSecure" => true,
        "save_card" => false,
        "description" => "Appointment Request Payment",
        "customer" => [
            "first_name" => $first_name,
            "last_name" => $last_name,
            "email" => $email,
            "phone" => [
                "country_code" => $phone_code,
                "number" => $phone_number
            ]
        ],
        "source" => [
            "id" => $token
        ],
        "redirect" => [
            "url" => $redirect_url
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.tap.company/v2/charges");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $tap_secret_key",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    // ==============================
    // SEND EMAIL IF SUCCESS
    // ==============================
    if (isset($result['status']) && in_array($result['status'], ['CAPTURED', 'AUTHORIZED'])) {

        $to = "d.kurien@tap.company";
        $subject = "New Appointment Request – Payment Completed";

        $message = "
        Charge ID: " . $result['id'] . "\n
        Amount: $amount $currency\n
        Name: $first_name $last_name\n
        Email: $email\n
        Phone: +$phone_code $phone_number\n
        Time: " . date("Y-m-d H:i:s") . "\n
        ";

        $headers = "From: no-reply@yourdomain.com";

        @mail($to, $subject, $message, $headers);
    }

    echo json_encode($result);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Appointment Request</title>
<script src="https://tap-sdks.b-cdn.net/card/1.0.2/index.js"></script>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f8fb;
    margin: 0;
}

.container {
    max-width: 420px;
    margin: 50px auto;
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

h2 {
    text-align: center;
    color: #1a73e8;
}

input, select {
    width: 100%;
    padding: 10px;
    margin: 8px 0;
    border-radius: 8px;
    border: 1px solid #ccc;
}

button {
    width: 100%;
    padding: 12px;
    background: #1a73e8;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
}

button:hover {
    background: #155ec4;
}

.hidden {
    display: none;
}

.success {
    text-align: center;
    padding: 40px;
}

.note {
    font-size: 12px;
    margin-top: 20px;
    color: #666;
    text-align: center;
}
</style>
</head>

<body>

<?php if (isset($_GET['completed'])): ?>

<div class="container success">
    <h2>✅ Appointment request received!</h2>
    <p>We will contact you soon.</p>
</div>

<?php else: ?>

<div class="container">

<h2>Request Appointment</h2>

<!-- ==============================
     STEP 1: USER FORM
============================== -->
<div id="form-section">
    <input type="number" id="amount" placeholder="Amount" step="0.01" required>

    <select id="currency">
        <option value="KWD">KWD</option>
        <option value="SAR">SAR</option>
        <option value="USD">USD</option>
    </select>

    <input type="text" id="first_name" placeholder="First Name">
    <input type="text" id="last_name" placeholder="Last Name">
    <input type="email" id="email" placeholder="Email">

    <input type="text" id="phone_code" placeholder="Country Code (e.g. 965)">
    <input type="text" id="phone_number" placeholder="Phone Number">

    <button onclick="startPayment()">Proceed to Payment →</button>
</div>

<!-- ==============================
     STEP 2: CARD SDK
============================== -->
<div id="card-section" class="hidden">
    <div id="card-sdk"></div>
    <br>
    <button id="pay-btn" class="hidden">Pay & Request Appointment</button>
</div>

<div class="note">
Replace API keys before going live. HTTPS is required for production.
</div>

</div>

<script>
// ==============================
// INIT PAYMENT FLOW
// ==============================
let card;

function startPayment() {

    document.getElementById("form-section").classList.add("hidden");
    document.getElementById("card-section").classList.remove("hidden");

    const amount = document.getElementById("amount").value;
    const currency = document.getElementById("currency").value;

    const firstName = document.getElementById("first_name").value;
    const lastName = document.getElementById("last_name").value;
    const email = document.getElementById("email").value;
    const phoneCode = document.getElementById("phone_code").value;
    const phoneNumber = document.getElementById("phone_number").value;

    // ==============================
    // INIT TAP CARD SDK
    // ==============================
    card = new Tapjsli('card-sdk', {
        publicKey: "pk_test_xxx", // Replace
        merchant: { id: "" },
        transaction: {
            amount: amount,
            currency: currency
        },
        customer: {
            first_name: firstName,
            last_name: lastName,
            email: email,
            phone: {
                country_code: phoneCode,
                number: phoneNumber
            }
        },
        acceptance: {
            supportedBrands: ["VISA", "MASTERCARD", "AMERICAN_EXPRESS", "MADA"]
        },
        fields: {
            cardHolder: true
        },
        style: {
            base: {
                color: "#333",
                fontSize: "16px"
            }
        }
    });

    card.onReady(() => {
        document.getElementById("pay-btn").classList.remove("hidden");
    });
}

// ==============================
// TOKENIZE & SEND TO SERVER
// ==============================
document.getElementById("pay-btn").addEventListener("click", function () {

    card.tokenize().then(function(result) {

        if (result.error) {
            alert(result.error.message);
            return;
        }

        const token = result.id;

        // Send to PHP
        fetch("", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: new URLSearchParams({
                token: token,
                amount: document.getElementById("amount").value,
                currency: document.getElementById("currency").value,
                first_name: document.getElementById("first_name").value,
                last_name: document.getElementById("last_name").value,
                email: document.getElementById("email").value,
                phone_code: document.getElementById("phone_code").value,
                phone_number: document.getElementById("phone_number").value
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.transaction && data.transaction.url) {
                window.location.href = data.transaction.url;
            } else {
                alert("Payment processing error");
            }
        });

    });
});
</script>

<?php endif; ?>

</body>
</html>
