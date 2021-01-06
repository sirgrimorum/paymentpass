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
    <form method="{{\Illuminate\Support\Arr::get($actionConfig,"method")}}" action="{{\Illuminate\Support\Arr::get($actionConfig,"action")}}" id="paymentPassForm" name="paymentPassForm">
        @if(\Illuminate\Support\Arr::get($actionConfig,"method") != "url")
        @foreach(\Illuminate\Support\Arr::get($actionConfig,"call_parameters",[]) as $parameter=>$value)
        @if (is_array($value))
        <input name="{{$parameter}}" type="hidden"  value="{{json_encode($value)}}"/>
        @else
        <input name="{{$parameter}}" type="hidden"  value="{{$value}}"/>
        @endif
        @endforeach
        @else
        <?php
        $parts = parse_url(\Illuminate\Support\Arr::get($actionConfig,"action"));
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
@push(\Illuminate\Support\Arr::get($config,"js_section"))
@if (\Illuminate\Support\Arr::get($config, "production", true))
<script>
    window.onload = function () {
        document.forms['paymentPassForm'].submit();
    };
</script>
@endif
@if (\Illuminate\Support\Arr::get($actionConfig, "con_script", false)!== false && \Illuminate\Support\Arr::get($actionConfig, "con_script", false)!= "")
{!! $actionConfig['con_script']  !!}
@endpush

