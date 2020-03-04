@extends(\Illuminate\Support\Arr::get($config,"redirect_blade_extend","layouts.app"))
@section(\Illuminate\Support\Arr::get($config,"redirect_blade_content_section","content"))
{!! \Illuminate\Support\Arr::get($config,"redirect_pre_html") !!}
<div style='{{\Illuminate\Support\Arr::get($config,"redirect_container_style")}}' class='{{\Illuminate\Support\Arr::get($config,"redirect_container_class")}}'>
    @if (request()->session()->has(\Illuminate\Support\Arr::get($config,"status_messages_key")))
    <div class="container">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="{{trans('paymentpass::paymentpass.labels.close')}}"><span aria-hidden="true">&times;</span></button>
            {!! request()->session()->pull(\Illuminate\Support\Arr::get($config,"status_messages_key")) !!}
        </div>
    </div>
    @endif
    @if (request()->session()->has(\Illuminate\Support\Arr::get($config,"error_messages_key")))
    <div class="container">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="{{trans('paymentpass::paymentpass.labels.close')}}"><span aria-hidden="true">&times;</span></button>
            {!! request()->session()->pull(\Illuminate\Support\Arr::get($config,"error_messages_key")) !!}
        </div>
    </div>
    @endif
    <form method="{{\Illuminate\Support\Arr::get($config,"service.method")}}" action="{{\Illuminate\Support\Arr::get($config,"service.action")}}" id="paymentPassForm" name="paymentPassForm">
        @if(\Illuminate\Support\Arr::get($config,"service.method") != "url")
        @if (\Illuminate\Support\Arr::get($config,"service.referenceCode.send",false))
        <input name="{{\Illuminate\Support\Arr::get($config,"service.referenceCode.field_name")}}" type="hidden"  value="{{\Illuminate\Support\Arr::get($config,"service.referenceCode.value")}}" >
        @endif
        @if (\Illuminate\Support\Arr::get($config,"service.signature.send",false))
        <input name="{{\Illuminate\Support\Arr::get($config,"service.signature.field_name")}}" type="hidden"  value="{{\Illuminate\Support\Arr::get($config,"service.signature.value")}}" >
        @endif
        @foreach(\Illuminate\Support\Arr::get($config,"service.responses",[]) as $response_name=>$response_datos)
            <input name="{{\Illuminate\Support\Arr::get($response_datos,"url_field_name","")}}" type="hidden"  value="{{\Illuminate\Support\Arr::get($response_datos,"url","")}}" >
        @endforeach
        @foreach(\Illuminate\Support\Arr::get($config,"service.parameters",[]) as $parameter=>$value)
        @if (is_array($value))
        <input name="{{$parameter}}" type="hidden"  value="{{json_encode($value)}}"/>
        @else
        <input name="{{$parameter}}" type="hidden"  value="{{$value}}"/>
        @endif
        @endforeach
        @else
        <?php
        $parts = parse_url(\Illuminate\Support\Arr::get($config,"service.action"));
        if (\Illuminate\Support\Arr::has($parts,"query")){
        parse_str($parts['query'], $query);
        ?>
        @if (is_array($query))
        @foreach($query as $parameter=> $value)
        <input name="{{$parameter}}" type="hidden"  value="{{$value}}"/>
        @endforeach
        @endif
        <?php
        }
        ?>
        @endif
        @if (!\Illuminate\Support\Arr::get($config,"production",true))
        <input name="Submit" class='btn btn-default' type="submit" value="Send"/>
        @endif
    </form>
</div>
{!! \Illuminate\Support\Arr::get($config,"redirect_post_html") !!}
@stop
@if (\Illuminate\Support\Arr::get($config, "production", true))
@push(\Illuminate\Support\Arr::get($config,"js_section"))
<script>
            window.onload = function () {
                document.forms['paymentPassForm'].submit();
            }
    ;
</script>
@endpush
@endif
