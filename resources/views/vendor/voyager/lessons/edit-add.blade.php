@extends('voyager::bread.edit-add')

@section('javascript')
    @parent
    
    @if(isset($preSelectedTraining))
    <script>
        $(document).ready(function() {
            setTimeout(function() {
                var $select = $('select[name="training_id"]');
                
                // Add mores option
                var option = new Option(
                    '{{ $preSelectedTraining->name }}',
                    '{{ $preSelectedTraining->id }}',
                    true,
                    true
                );
                
                $select.empty().append(option).trigger('change');
            }, 300);
        });
    </script>
    @endif
@endsection