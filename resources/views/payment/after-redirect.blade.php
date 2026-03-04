<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Title of the document</title>
</head>

<body>
<h1>Success Payment</h1>
</body>
<script>
    window.location.href = '{{ route('redirect-after-cashu-payment') }}';
</script>
</html>