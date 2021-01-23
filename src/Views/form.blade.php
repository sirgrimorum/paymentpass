<?php
$serviceId = ucfirst($service) . "_" . \Illuminate\Support\Str::random(5);
?>
<style>
    .alert:empty{
        display: none;
    }
</style>
<form
    action='{{ \Illuminate\Support\Arr::get($formConfig, "action", "") }}'
    method='{{ \Illuminate\Support\Arr::get($formConfig, "method", "POST") }}'
    id='{{ \Illuminate\Support\Arr::get($formConfig, "id", "form_paymentpass_$serviceId") }}'
    class='{{ \Illuminate\Support\Arr::get($formConfig, "class", "") }}'
    >
    @foreach(\Illuminate\Support\Arr::get($formConfig, "fields", []) as $field)
    <?php
    $error_campo = false;
    $claseError = 'is-valid';
    ?>
    @if(\Illuminate\Support\Arr::get($field, "type", "") == 'script')
    <script
        @foreach(\Illuminate\Support\Arr::get($field, "attributes", []) as $attribute)
        {!! $attribute !!}
        @endforeach
        >@if(\Illuminate\Support\Arr::get($field, "content", "") !== null){!! \Illuminate\Support\Arr::get($field, "content", "") !!}@endif
    </script>
    @elseif(\Illuminate\Support\Arr::get($field, "type", "") == 'style')
    <style
        @foreach(\Illuminate\Support\Arr::get($field, "attributes", []) as $attribute)
        {!! $attribute !!}
        @endforeach
        >@if(\Illuminate\Support\Arr::get($field, "content", "") !== null){!! \Illuminate\Support\Arr::get($field, "content", "") !!}@endif
    </style>
    @elseif(\Illuminate\Support\Arr::get($field, "type", "") == 'div' && \Illuminate\Support\Arr::get($field, "label", "") == "")
    <div
        @foreach(\Illuminate\Support\Arr::get($field, "attributes", []) as $attribute)
        {!! $attribute !!}
        @endforeach
        >@if(\Illuminate\Support\Arr::get($field, "content", "") !== null){!! \Illuminate\Support\Arr::get($field, "content", "") !!}@endif</div>
    @elseif(\Illuminate\Support\Arr::get($field, "type", "") == 'hidden')
    <input
        {{-- class="{{ $claseError }}" --}}
        type='{{ \Illuminate\Support\Arr::get($field, "type", "hidden") }}'
        @foreach(\Illuminate\Support\Arr::get($field, "attributes", []) as $attribute)
        {!! $attribute !!}
        @endforeach
    />
    @elseif(\Illuminate\Support\Arr::get($field, "type", "") != "")
    <div class='{{ \Illuminate\Support\Arr::get($field, "div_class", "form-group row") }}'>
        <div class='{{ \Illuminate\Support\Arr::get($field, "div_label_class", "col-xs-12 col-sm-4 col-md-2") }}'>
            <label class='{{ \Illuminate\Support\Arr::get($field, "label_class", "col-xs-12 col-sm-4 col-md-2") }}'>
                {!! ucfirst(\Illuminate\Support\Arr::get($field, "label", "")) !!}
            </label>
            @if (isset($field['description']))
            <small class="form-text text-muted mt-0">
                {!! $field['description'] !!}
            </small>
            @endif
        </div>
        <div class='{{ \Illuminate\Support\Arr::get($field, "div_input_class", "col-xs-12 col-sm-8 col-md-10") }}'>
            @if(\Illuminate\Support\Arr::get($field, "type", "") == 'div')
            <div
                @foreach(\Illuminate\Support\Arr::get($field, "attributes", []) as $attribute)
                {!! $attribute !!}
                @endforeach
                >@if(\Illuminate\Support\Arr::get($field, "content", "") !== null){!! \Illuminate\Support\Arr::get($field, "content", "") !!}@endif</div>
            @else
                @if (\Illuminate\Support\Arr::get($field, "pre", "") != "" || \Illuminate\Support\Arr::get($field, "post", "") != "")
                <div class="input-group {{ \Illuminate\Support\Arr::get($field, "div_input_group_class", "") }} {{ $claseError }}">
                    @endif
                    @if (\Illuminate\Support\Arr::get($field, "pre", "") != "")
                    @if (is_array($field["pre"]))
                    <div class="input-group-prepend"><div class="input-group-text" id="{{ array_keys($field["pre"][0]) }}">{!! $field["pre"][array_keys($field["pre"][0])] !!}</div></div>
                    @else
                    <div class="input-group-prepend"><div class="input-group-text">{!! $field["pre"] !!}</div></div>
                    @endif
                    @endif
                    <input
                        {{-- class="{{ $claseError }}" --}}
                        type='{{ \Illuminate\Support\Arr::get($field, "type", "text") }}'
                        @foreach(\Illuminate\Support\Arr::get($field, "attributes", []) as $attribute)
                        {!! $attribute !!}
                        @endforeach
                    />
                    @if (\Illuminate\Support\Arr::get($field, "post", "") != "")
                    @if (is_array($field["post"]))
                    <div class="input-group-append"><div class="input-group-text" id="{{ array_keys($field["post"])[0] }}">{!! $field["post"][array_keys($field["post"])[0]] !!}</div></div>
                    @else
                    <div class="input-group-append"><div class="input-group-text">{!! $field["post"] !!}</div></div>
                    @endif
                    @endif
                    @if (\Illuminate\Support\Arr::get($field, "pre", "") != "" || \Illuminate\Support\Arr::get($field, "post", "") != "")
                </div>
                @endif
            @endif
            <div class="invalid-feedback">
            </div>
            @if(isset($field["help"]))
            <small class="form-text text-muted mt-0">
                {!! $field["help"] !!}
            </small>
            @endif
        </div>
    </div>
    @endif
    @endforeach
    <div class='{{ \Illuminate\Support\Arr::get($formConfig, "div_class", "form-group row") }}'>
        <div class='{{ \Illuminate\Support\Arr::get($formConfig, "button_div_class", "col-xs-offset-0 col-sm-offset-4 col-md-offset-2 col-xs-12 col-sm-8 col-md-10") }}'>
            <button class='{{ \Illuminate\Support\Arr::get($formConfig, "button_class", "btn btn-primary") }}' type="submit" form='{{ \Illuminate\Support\Arr::get($formConfig, "id", "form_paymentpass_$serviceId") }}'>
                {{ \Illuminate\Support\Arr::get($formConfig, "button_label", "Guardar") }}
            </button>
        </div>
    </div>
</form>
<script>
    window.addEventListener ? window.addEventListener("load",paymentpass_{{ $serviceId }}Inicio,false) : window.attachEvent && window.attachEvent("onload",paymentpass_{{ $serviceId }}Inicio);
    function paymentpass_{{ $serviceId }}Inicio() {
        @if (count(\Illuminate\Support\Arr::get($formConfig, "include_scripts", [])) > 0)
            var pathScript = '{{ $formConfig["include_scripts"][0] }}';
            scriptLoader(pathScript,false,"");
            @if(count($formConfig["include_scripts"])>1)
            scriptLoaderCreator(pathScript.split('/').pop().split('#')[0].split('?')[0].replaceAll('.','_'),"cargarScript1Para{{ $serviceId }}();");
            @else
            scriptLoaderCreator(pathScript.split('/').pop().split('#')[0].split('?')[0].replaceAll('.','_'),"todoCargadoPara{{ $serviceId }}();");
            @endif
        @else
            todoCargadoPara{{ $serviceId }}();
        @endif

        $('#{{ \Illuminate\Support\Arr::get($formConfig, "id", "paymentpass_$serviceId") }}').on('submit', function(formEvent){
            //stops submit
            event.preventDefault();
            //gets form content
            var $form = $(this);
            //disables buttons
            $form.find("button").prop("disabled", true);
            @if(\Illuminate\Support\Arr::get($formConfig, "type", "ajax") == "script" && \Illuminate\Support\Arr::get($formConfig, "function_call", "") != "")
            {!! $formConfig["function_call"] !!}
            @else
            var url = $form.attr('action');
            var method = $form.attr('method');
            $.ajax({
                type: method,
                url: url,
                dataType : 'json',
                data: $form.serialize(), // serializes the form's elements.
                success: function (data) {
                    console.log('Submission was successful.');
                    console.log(data);
                },
                error: function (data) {
                    console.log('An error occurred.');
                    console.log(data);
                },
            });
            @endif
        });
    }

    function todoCargadoPara{{ $serviceId }}(){
        @foreach (\Illuminate\Support\Arr::get($formConfig, "pre_functions", []) as $pre_function => $params)
        {{ $pre_function }}(
            {!! \Sirgrimorum\PaymentPass\PaymentPassTranslator::paramsForJs($params, true) !!}
        );
        @endforeach
    }

    @if (count(\Illuminate\Support\Arr::get($formConfig, "include_scripts", [])) > 0)
    @foreach ($formConfig["include_scripts"] as $include_script)
    @if (!$loop->first)
    function cargarScript{{ $loop->index }}Para{{ $serviceId }}(){
        var pathScript = '{{ $include_script }}';
        scriptLoader(pathScript,false,"");
        @if (!$loop->last)
        scriptLoaderCreator(pathScript.split('/').pop().split('#')[0].split('?')[0].replaceAll('.','_'),"cargarScript{{ $loop->iteration }}Para{{ $serviceId }}();");
        @else
        scriptLoaderCreator(pathScript.split('/').pop().split('#')[0].split('?')[0].replaceAll('.','_'),"todoCargadoPara{{ $serviceId }}();");
        @endif
    }
    @endif
    @endforeach
    if (typeof window['scriptLoaderCreator'] !== 'function') {
        function scriptLoaderCreator(callbackName, functionBody){if(!(callbackName in callbacksFunctions)){callbacksFunctions[callbackName] = [];}callbacksFunctions[callbackName].push(new Function(functionBody));}function scriptLoaderRunner(callbackName){if(callbackName in callbacksFunctions){for (var i = 0; i < callbacksFunctions[callbackName].length; i++){callbacksFunctions[callbackName][i]();}}}function scriptLoader(path, diferir, inner=''){let scripts = Array .from(document.querySelectorAll('script')).map(scr => scr.src);var callbackName = inner;if (inner=='' && path != ''){callbackName = path.split('/').pop().split('#')[0].split('?')[0].replaceAll('.','_');}if (!scripts.includes(path) || path == ''){var tag = document.createElement('script');tag.type = 'text/javascript';if (callbackName!= ''){if(tag.readyState) {tag.onreadystatechange = function() {if ( tag.readyState === 'loaded' || tag.readyState === 'complete' ) {tag.onreadystatechange = null;scriptLoaderRunner(callbackName);}};}else{tag.onload = function() {scriptLoaderRunner(callbackName);};}}if (path != ''){tag.src = path;}if (diferir){var attd = document.createAttribute('defer');tag.setAttributeNode(attd);}if (inner != ''){var innerBlock = document.getElementById(inner);if (typeof innerBlock !== 'undefined' && innerBlock !== null){tag.innerHTML = innerBlock.innerHTML;}}document.getElementsByTagName('body')[document.getElementsByTagName('body').length-1].appendChild(tag);}else{if (callbackName!= ''){if(callbackName in window){window[callbackName]();}}}}
    }
    @endif
</script>
