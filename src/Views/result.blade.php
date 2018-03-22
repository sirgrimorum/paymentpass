@extends(array_get($config,"result_blade_extend","layouts.app"))
@section(array_get($config,"result_blade_content_section","content"))
{!! array_get($config,"result_pre_html") !!}
<div style='{{array_get($config,"result_container_style")}}' class='{{array_get($config,"result_container_class")}}'>
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
</div>
{!! array_get($config,"result_post_html") !!}
@stop