@extends(array_get($config,"redirect_blade_extend","layouts.app"))
@section(array_get($config,"redirect_blade_content_section","content"))
{!! array_get($config,"redirect_pre_html") !!}
<div style='{{array_get($config,"redirect_container_style")}}' class='{{array_get($config,"redirect_container_class")}}'>
    @if (session(array_get($config,"status_messages_key")))
    <div class="container">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="{{trans('paymentpass::labels.close')}}"><span aria-hidden="true">&times;</span></button>
            {!! session(array_get($config,"status_messages_key")) !!}
        </div>
    </div>
    @endif
    @if (session(array_get($config,"error_messages_key")))
    <div class="container">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="{{trans('paymentpass::labels.close')}}"><span aria-hidden="true">&times;</span></button>
            {!! session(array_get($config,"error_messages_key")) !!}
        </div>
    </div>
    @endif
    <form method="{{array_get($config,"service.method")}}" action="{{array_get($config,"service.action")}}" id="paymentPassForm" name="paymentPassForm">
        @if (array_get($config,"service.referenceCode.send",false))
        <input name="{{array_get($config,"service.referenceCode.fieldname")}}" type="hidden"  value="{{array_get($config,"service.referenceCode.value")}}" >
        @endif
        @if (array_get($config,"service.signature.active",false))
        <input name="{{array_get($config,"service.signature.fieldname")}}" type="hidden"  value="{{array_get($config,"service.signature.value")}}" >
        @endif
        @foreach(array_get($config,"service.parameters",[]) as $parameter=>$value)
        <input name="{{$parameter}}"    type="hidden"  value="{{$value}}"/>
        @endif
        @if (!array_get($config,"production",true))
        <input name="Submit" class='btn btn-default' type="submit" value="Send"/>
        @endif
    </form>
</div>
{!! array_get($config,"redirect_post_html") !!}
@stop
@push(array_get($config,"js_section"))
<script>
    @if (!array_get($config, "production", true))
            window.onload = function () {
                document.forms['paymentPassForm'].submit();
            }
    ;
    @endif
</script>
@endpush