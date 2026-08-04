@extends('errors.layout')

@section('code', '429')
@section('heading', 'Too many requests')
@section('message', 'This request was rate-limited. Wait briefly before trying again.')
