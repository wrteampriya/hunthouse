@extends('layouts.main')

@section('title')
    {{ __('Calculator') }}
@endsection

@section('page-title')
    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="divider">
                    <div class="divider-text">
                        <h4>{{ __('Calculator') }}</h4>
                    </div>
                </div>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            {!! Form::open(['files' => true]) !!}
                            <div class="form-group row">
                                {{ Form::label('from_options', trans('Type'), ['class' => 'col-sm-2 col-form-label text-center']) }}
                                <div class="col-sm-4">
                                    <select name="from_options" id="from_options" class="form-select form-control-sm">
                                        <option value=""> {{ __('Select Option') }} </option>
                                        <option value="Square Feet">{{ __('Square Feet') }}</option>
                                        <option value="Square Meter">{{ __('Square Meter') }}</option>
                                        <option value="Acre">{{ __('Acre') }}</option>
                                        <option value="Hectare">{{ __('Hectare') }}</option>
                                        <option value="Gaj">{{ __('Gaj') }}</option>
                                        <option value="Bigha">{{ __('Bigha') }}</option>
                                        <option value="Cent">{{ __('Cent') }}</option>
                                        <option value="Katha">{{ __('Katha') }}</option>
                                        <option value="Guntha">{{ __('Guntha') }}</option>
                                    </select>

                                </div>

                                {{ Form::label('num_of_unit', trans('Number Of Unit'), ['class' => 'col-sm-2 col-form-label text-center']) }}
                                <div class="col-sm-4">
                                    {{ Form::number('NumberOfUnits', '', ['class' => 'form-control', 'placeholder' => trans('Number Of Unit'), 'id' => 'num_of_unit', 'required' => true]) }}
                                </div>
                            </div>
                            <hr>
                            <div class="form-group row">
                                {{ Form::label('to_options', trans('Type'), ['class' => 'col-sm-2 col-form-label text-center']) }}
                                <div class="col-sm-4">
                                    <select name="to_options" id="to_options" class="form-select form-control-sm">
                                        <option value=""> {{ __('Select Option') }} </option>
                                        <option value="Square Feet">{{ __('Square Feet') }}</option>
                                        <option value="Square Meter">{{ __('Square Meter') }}</option>
                                        <option value="Acre">{{ __('Acre') }}</option>
                                        <option value="Hectare">{{ __('Hectare') }}</option>
                                        <option value="Gaj">{{ __('Gaj') }}</option>
                                        <option value="Bigha">{{ __('Bigha') }}</option>
                                        <option value="Cent">{{ __('Cent') }}</option>
                                        <option value="Katha">{{ __('Katha') }}</option>
                                        <option value="Guntha">{{ __('Guntha') }}</option>
                                    </select>
                                </div>

                                {{ Form::label('converted_figure', trans('Converted Figure'), ['class' => 'col-sm-2 col-form-label text-center']) }}
                                <div class="col-sm-4">
                                    {{ Form::text('Converted Figure', '', ['class' => 'form-control', 'placeholder' => trans('Converted Figure'), 'id' => 'converted_figure', 'readonly' => true]) }}
                                </div>
                            </div>
                            {!! Form::close() !!}
                        </div>
                    </div>

                </div>
            </div>
        </div>



    </section>
@section('script')
    <script>
        (function () {
            function formatNumber(value) {
                var num = parseFloat(value);
                if (!isFinite(num)) return '';
                return num.toFixed(6).replace(/\.0+$|(?<=\.[0-9]*?)0+$/g, '').replace(/\.$/, '');
            }

            function setResult(value) {
                $('#converted_figure').val(formatNumber(value));
            }

            function calculate() {
                var converted_from = $('#from_options').val();
                var convert_to = $('#to_options').val();
                var no_of_unit_str = $('#num_of_unit').val();
                var no_of_unit = parseFloat(no_of_unit_str);

                // Reset when inputs are incomplete or invalid
                if (!converted_from || !convert_to || !no_of_unit_str || !isFinite(no_of_unit) || no_of_unit < 0) {
                    setResult('');
                    return;
                }

                // Same unit → echo value
                if (converted_from === convert_to) {
                    setResult(no_of_unit);
                    return;
                }

                var ans;

                //Square Feet <----------->Square Meter
                if (converted_from == "Square Feet" && convert_to == "Square Meter") {
                    ans = (no_of_unit * 0.092903);
                    setResult(ans);
                }
                if (converted_from == "Square Meter" && convert_to == "Square Feet") {
                    ans = (no_of_unit * 10.763915);
                    setResult(ans);
                }

                //Square Feet <----------->Acre
                if (converted_from == "Square Feet" && convert_to == "Acre") {
                    ans = (no_of_unit * 0.00002295);
                    setResult(ans);
                }
                if (converted_from == "Acre" && convert_to == "Square Feet") {
                    ans = (no_of_unit * 43560.057264);
                    setResult(ans);
                }

                //Square Feet <----------->Hectare
                if (converted_from == "Square Feet" && convert_to == "Hectare") {
                    ans = (no_of_unit * 0.000009);
                    setResult(ans);
                }
                if (converted_from == "Hectare" && convert_to == "Square Feet") {
                    ans = (no_of_unit * 107639.150512);
                    setResult(ans);
                }

                //Square Feet <----------->Gaj
                if (converted_from == "Square Feet" && convert_to == "Gaj") {
                    ans = (no_of_unit * 0.112188);
                    setResult(ans);
                }
                if (converted_from == "Gaj" && convert_to == "Square Feet") {
                    ans = (no_of_unit * 8.913598);
                    setResult(ans);
                }

                //Square Feet <----------->Bigha
                if (converted_from == "Square Feet" && convert_to == "Bigha") {
                    ans = (no_of_unit * 0.000037);
                    setResult(ans);
                }
                if (converted_from == "Bigha" && convert_to == "Square Feet") {
                    ans = (no_of_unit * 27000.010764);
                    setResult(ans);
                }

                //Square Feet <----------->Cent
                if (converted_from == "Square Feet" && convert_to == "Cent") {
                    ans = (no_of_unit * 0.002296);
                    setResult(ans);
                }
                if (converted_from == "Cent" && convert_to == "Square Feet") {
                    ans = (no_of_unit * 435.508003);
                    setResult(ans);
                }

                //Square Feet <----------->Katha
                if (converted_from == "Square Feet" && convert_to == "Katha") {
                    ans = (no_of_unit * 0.000735);
                    setResult(ans);
                }
                if (converted_from == "Katha" && convert_to == "Square Feet") {
                    ans = (no_of_unit * 1361.000614);
                    setResult(ans);
                }

                //Square Feet <----------->Guntha
                if (converted_from == "Square Feet" && convert_to == "Guntha") {
                    ans = (no_of_unit * 0.0009182);
                    setResult(ans);
                }
                if (converted_from == "Guntha" && convert_to == "Square Feet") {
                    ans = (no_of_unit * 1089.000463);
                    setResult(ans);
                }

                //Square Meter <----------->Acre
                if (converted_from == "Square Meter" && convert_to == "Acre") {
                    ans = (no_of_unit * 0.00024677419354838707);
                    setResult(ans);
                }
                if (converted_from == "Acre" && convert_to == "Square Meter") {
                    ans = (no_of_unit * 4046.860000);
                    setResult(ans);
                }

                //Square Meter <----------->Hectare
                if (converted_from == "Square Meter" && convert_to == "Hectare") {
                    ans = (no_of_unit * 0.000100);
                    setResult(ans);
                }
                if (converted_from == "Hectare" && convert_to == "Square Meter") {
                    ans = (no_of_unit * 10000.000000);
                    setResult(ans);
                }

                //Square Meter <----------->Gaj
                if (converted_from == "Square Meter" && convert_to == "Gaj") {
                    ans = (no_of_unit * 1.207584);
                    setResult(ans);
                }
                if (converted_from == "Gaj" && convert_to == "Square Meter") {
                    ans = (no_of_unit * 0.828100);
                    setResult(ans);
                }

                //Square Meter <----------->Bigha
                if (converted_from == "Square Meter" && convert_to == "Bigha") {
                    ans = (no_of_unit * 0.000399);
                    setResult(ans);
                }
                if (converted_from == "Bigha" && convert_to == "Square Meter") {
                    ans = (no_of_unit * 2508.382000);
                    setResult(ans);
                }

                //Square Meter <----------->Cent
                if (converted_from == "Square Meter" && convert_to == "Cent") {
                    ans = (no_of_unit * 0.024688172043010752);
                    setResult(ans);
                }
                if (converted_from == "Cent" && convert_to == "Square Meter") {
                    ans = (no_of_unit * 40.460000);
                    setResult(ans);
                }

                //Square Meter <----------->Katha
                if (converted_from == "Square Meter" && convert_to == "Katha") {
                    ans = (no_of_unit * 0.007909);
                    setResult(ans);
                }
                if (converted_from == "Katha" && convert_to == "Square Meter") {
                    ans = (no_of_unit * 126.441040);
                    setResult(ans);
                }

                //Square Meter <----------->Guntha
                if (converted_from == "Square Meter" && convert_to == "Guntha") {
                    ans = (no_of_unit * 0.009884);
                    setResult(ans);
                }
                if (converted_from == "Guntha" && convert_to == "Square Meter") {
                    ans = (no_of_unit * 101.171410);
                    setResult(ans);
                }

                //Acre <----------->Hectare
                if (converted_from == "Acre" && convert_to == "Hectare") {
                    ans = (no_of_unit * 0.404686);
                    setResult(ans);
                }
                if (converted_from == "Hectare" && convert_to == "Acre") {
                    ans = (no_of_unit * 2.4710538146717);
                    setResult(ans);
                }

                //Acre <----------->Gaj
                if (converted_from == "Acre" && convert_to == "Gaj") {
                    ans = (no_of_unit * 4886.921869);
                    setResult(ans);
                }
                if (converted_from == "Gaj" && convert_to == "Acre") {
                    ans = (no_of_unit * 0.000205);
                    setResult(ans);
                }

                //Acre <----------->Bigha
                if (converted_from == "Acre" && convert_to == "Bigha") {
                    ans = (no_of_unit * 1.613335);
                    setResult(ans);
                }
                if (converted_from == "Bigha" && convert_to == "Acre") {
                    ans = (no_of_unit * 0.619834);
                    setResult(ans);
                }

                //Acre <----------->Cent
                if (converted_from == "Acre" && convert_to == "Cent") {
                    ans = (no_of_unit * 100.021256);
                    setResult(ans);
                }
                if (converted_from == "Cent" && convert_to == "Acre") {
                    ans = (no_of_unit * 0.009998);
                    setResult(ans);
                }

                //Acre <----------->Katha
                if (converted_from == "Acre" && convert_to == "Katha") {
                    ans = (no_of_unit * 32.005906);
                    setResult(ans);
                }
                if (converted_from == "Katha" && convert_to == "Acre") {
                    ans = (no_of_unit * 0.031244);
                    setResult(ans);
                }

                //Acre <----------->Guntha
                if (converted_from == "Acre" && convert_to == "Guntha") {
                    ans = (no_of_unit * 40.000036);
                    setResult(ans);
                }
                if (converted_from == "Guntha" && convert_to == "Acre") {
                    ans = (no_of_unit * 0.025000);
                    setResult(ans);
                }

                //Hectare <----------->Gaj
                if (converted_from == "Hectare" && convert_to == "Gaj") {
                    ans = (no_of_unit * 12075.836252);
                    setResult(ans);
                }
                if (converted_from == "Gaj" && convert_to == "Hectare") {
                    ans = (no_of_unit * 0.000083);
                    setResult(ans);
                }

                //Hectare <----------->Bigha
                if (converted_from == "Hectare" && convert_to == "Bigha") {
                    ans = (no_of_unit * 3.986634);
                    setResult(ans);
                }
                if (converted_from == "Bigha" && convert_to == "Hectare") {
                    ans = (no_of_unit * 0.250838);
                    setResult(ans);
                }

                //Hectare <----------->Cent
                if (converted_from == "Hectare" && convert_to == "Cent") {
                    ans = (no_of_unit * 247.157687);
                    setResult(ans);
                }
                if (converted_from == "Cent" && convert_to == "Hectare") {
                    ans = (no_of_unit * 0.004046);
                    setResult(ans);
                }

                //Hectare <----------->Katha
                if (converted_from == "Hectare" && convert_to == "Katha") {
                    ans = (no_of_unit * 79.088245);
                    setResult(ans);
                }
                if (converted_from == "Katha" && convert_to == "Hectare") {
                    ans = (no_of_unit * 0.012644);
                    setResult(ans);
                }

                //Hectare <----------->Guntha
                if (converted_from == "Hectare" && convert_to == "Guntha") {
                    ans = (no_of_unit * 98.842153);
                    setResult(ans);
                }
                if (converted_from == "Guntha" && convert_to == "Hectare") {
                    ans = (no_of_unit * 0.010117);
                    setResult(ans);
                }

                //Gaj <----------->Bigha
                if (converted_from == "Gaj" && convert_to == "Bigha") {
                    ans = (no_of_unit * 0.000330);
                    setResult(ans);
                }
                if (converted_from == "Bigha" && convert_to == "Gaj") {
                    ans = (no_of_unit * 3029.081029);
                    setResult(ans);
                }

                //Gaj <----------->Cent
                if (converted_from == "Gaj" && convert_to == "Cent") {
                    ans = (no_of_unit * 0.020467);
                    setResult(ans);
                }
                if (converted_from == "Cent" && convert_to == "Gaj") {
                    ans = (no_of_unit * 48.858833);
                    setResult(ans);
                }

                //Gaj <----------->Katha
                if (converted_from == "Gaj" && convert_to == "Katha") {
                    ans = (no_of_unit * 0.006549);
                    setResult(ans);
                }
                if (converted_from == "Katha" && convert_to == "Gaj") {
                    ans = (no_of_unit * 152.688129);
                    setResult(ans);
                }

                //Gaj <----------->Guntha
                if (converted_from == "Gaj" && convert_to == "Guntha") {
                    ans = (no_of_unit * 0.008185);
                    setResult(ans);
                }
                if (converted_from == "Guntha" && convert_to == "Gaj") {
                    ans = (no_of_unit * 122.172938);
                    setResult(ans);
                }

                //Bigha <----------->Cent
                if (converted_from == "Bigha" && convert_to == "Cent") {
                    ans = (no_of_unit * 61.996589);
                    setResult(ans);
                }
                if (converted_from == "Cent" && convert_to == "Bigha") {
                    ans = (no_of_unit * 0.016130);
                    setResult(ans);
                }

                //Bigha <----------->Katha
                if (converted_from == "Bigha" && convert_to == "Katha") {
                    ans = (no_of_unit * 19.838353);
                    setResult(ans);
                }
                if (converted_from == "Katha" && convert_to == "Bigha") {
                    ans = (no_of_unit * 0.050407);
                    setResult(ans);
                }

                //Cent <----------->Katha
                if (converted_from == "Cent" && convert_to == "Katha") {
                    ans = (no_of_unit * 0.319991);
                    setResult(ans);
                }
                if (converted_from == "Katha" && convert_to == "Cent") {
                    ans = (no_of_unit * 3.125087);
                    setResult(ans);
                }

                //Cent <----------->Guntha
                if (converted_from == "Cent" && convert_to == "Guntha") {
                    ans = (no_of_unit * 0.399915);
                    setResult(ans);
                }
                if (converted_from == "Guntha" && convert_to == "Cent") {
                    ans = (no_of_unit * 2.500529);
                    setResult(ans);
                }

                //Katha <----------->Guntha
                if (converted_from == "Katha" && convert_to == "Guntha") {
                    ans = (no_of_unit * 1.249770);
                    setResult(ans);
                }
                if (converted_from == "Guntha" && convert_to == "Katha") {
                    ans = (no_of_unit * 0.800147);
                    setResult(ans);
                }
            }

            $('#from_options, #to_options').on('change', calculate);
            $('#num_of_unit').on('change keyup input', calculate);
        })();
    </script>
@endsection
@endsection
