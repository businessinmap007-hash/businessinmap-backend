<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Title of the document</title>
</head>

<body>




<form id="payForm" action="https://www.cashu.com/cgi-bin/payment/pcashu.cgi" method="post" style="text-align: center;height: 65px;">
    <input type="hidden" name="Transaction_Code" value="{{ $Transaction_Code }}">
    <noscript>
        <input type="submit" name="but" value="Pay with CASHU!">
    </noscript>
    <p>
        <strong>
            دفع بإستخدام بوابة كاش يو
        </strong>
    </p>
    <input type="submit" name="but" value="Pay with CASHU!" style="
    display: block;
    height: 37px;
    margin: 0 auto;
    background: #2196F3;
    color: #ffff;
    width: 149px;
    border-radius: 20px;
    margin-top: 35px;
">

</form>


<script>


    // document.write("Please wait while been redirected to CashU...");
    // document.getElementById("payForm").submit();


</script>
</body>

</html>

