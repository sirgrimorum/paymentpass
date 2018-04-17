{
"action":"{{array_get($config,"service.action")}}",
"method":"{{array_get($config,"service.method")}}",
"parameters":{
@if(array_get($config,"service.method") != "url")
@if (array_get($config,"service.referenceCode.send",false))
"{{array_get($config,"service.referenceCode.field_name")}}":{{array_get($config,"service.referenceCode.value")}}",
@endif
@if (array_get($config,"service.signature.send",false))
"{{array_get($config,"service.signature.field_name")}}":"{{array_get($config,"service.signature.value")}}",
@endif
@foreach(array_get($config,"service.responses",[]) as $response_name=>$response_datos)
"{{array_get($response_datos,"url_field_name","")}}":"{{array_get($response_datos,"url","")}}",
@endforeach
@foreach(array_get($config,"service.parameters",[]) as $parameter=>$value)
@if (is_array($value))
"{{$parameter}}":"{{json_encode($value)}}",
@else
"{{$parameter}}":"{{$value}}",
@endif
@endforeach
@else
<?php
$parts = parse_url(array_get($config, "service.action"));
if (array_has($parts, "query")) {
    parse_str($parts['query'], $query);
    ?>
    @if (is_array($query))
    @foreach($query as $parameter=> $value)
    "{{$parameter}}":"{{$value}}",
    @endforeach
    @endif
    <?php
}
?>
@endif


