@extends(\Illuminate\Support\Arr::get($config,"result_blade_extend","layouts.app"))
@section(\Illuminate\Support\Arr::get($config,"result_blade_content_section","content"))
{!! \Illuminate\Support\Arr::get($config,"result_pre_html") !!}
<div style='{{\Illuminate\Support\Arr::get($config,"result_container_style")}}' class='{{\Illuminate\Support\Arr::get($config,"result_container_class")}}'>
    @if ($request->session()->has(\Illuminate\Support\Arr::get($config,"status_messages_key")))
    <div class="container">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="{{trans('paymentpass::paymentpass.labels.close')}}"><span aria-hidden="true">&times;</span></button>
            {!! $request->session()->pull(\Illuminate\Support\Arr::get($config,"status_messages_key")) !!}
        </div>
    </div>
    @endif
    @if ($request->session()->has(\Illuminate\Support\Arr::get($config,"error_messages_key")))
    <div class="container">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="{{trans('paymentpass::paymentpass.labels.close')}}"><span aria-hidden="true">&times;</span></button>
            {!! $request->session()->pull(\Illuminate\Support\Arr::get($config,"error_messages_key")) !!}
        </div>
    </div>
    @endif
</div>
{!! \Illuminate\Support\Arr::get($config,"result_post_html") !!}
@stop