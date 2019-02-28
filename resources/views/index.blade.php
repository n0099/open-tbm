@extends('layout')
@php($baseUrl = env('APP_URL'))

@section('title', '首页')

@section('container')
    <div class="text-center" style="font-size: 8em">WIP</div>
    <div class="text-center" style="font-size: 2em">开发中</div>
    <div class="text-center" style="font-size: 2em">请访问其他页面</div>
@endsection

@section('script-after-container')
    <script>
        new Vue({ el: '#navbar' , data: { $$baseUrl, activeNav: 'index' } });
    </script>
@endsection