@extends('layouts.app')

@section('header-layout')
    @include('components.navigation.doc-type-tabs', ['seccion' => $tipoSeccionReporte ?? 'reportes'])
@endsection
