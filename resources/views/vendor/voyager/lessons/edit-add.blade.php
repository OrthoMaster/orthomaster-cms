@extends('voyager::bread.edit-add')

@section('javascript')
    @parent
    
    @if(isset($preSelectedModule))
    <script>
        $(document).ready(function() {
            setTimeout(function() {
                var $select = $('select[name="module_id"]');
                
                // Add mores option
                var option = new Option(
                    '{{ $preSelectedModule->name }}',
                    '{{ $preSelectedModule->id }}',
                    true,
                    true
                );
                
                $select.empty().append(option).trigger('change');
            }, 300);
        });
    </script>
    @endif
@endsection