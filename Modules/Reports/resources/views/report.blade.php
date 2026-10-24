{{-- Document shell shared by every template: the template supplies its styles and layout. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $research->name }} | Supplement research report</title>
    @include("reports::templates.{$template}.styles")
</head>
<body>
    @include("reports::templates.{$template}.layout")
</body>
</html>
