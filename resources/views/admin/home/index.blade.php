@extends('admin.layouts.master')

@section('title', 'الصفحة الرئيسية')

@section('styles')
    <style>
        .dashboard-title {
            font-weight: 700;
            margin-bottom: 20px;
        }

        .dashboard-card-link {
            text-decoration: none;
            color: inherit;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            border: 1px solid #eee;
            transition: all .25s ease-in-out;
            min-height: 150px;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            font-size: 42px;
            color: #1e88e5;
        }

        .stat-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 4px;
            color: #111;
        }

        .stat-desc {
            font-size: 13px;
            color: #888;
        }

        .statistics-row {
            margin-top: 10px;
        }
    </style>
@endsection

@section('content')

    <!-- Page Title -->
    <div class="row">
        <div class="col-sm-12">
            <h4 class="page-title dashboard-title">@lang('maincp.control_panel')</h4>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row statistics-row">

        {{-- مديري النظام --}}
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('users.index') }}" class="dashboard-card-link">
                <div class="card-box stat-card">
                    <div class="row">
                        <div class="col-xs-8">
                            <div class="stat-title">إدارة النظام</div>
                            <div class="stat-value">
                                {{ \App\Models\User::whereType('admin')->count() }}
                            </div>
                            <div class="stat-desc">عدد مديري النظام</div>
                        </div>
                        <div class="col-xs-4 text-center">
                            <i class="zmdi zmdi-accounts stat-icon"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- العملاء --}}
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('business.index') }}" class="dashboard-card-link">
                <div class="card-box stat-card">
                    <div class="row">
                        <div class="col-xs-8">
                            <div class="stat-title">العملاء</div>
                            <div class="stat-value">
                                {{ \App\Models\User::whereIn('type', ['business', 'client'])->count() }}
                            </div>
                            <div class="stat-desc">إجمالي العملاء</div>
                        </div>
                        <div class="col-xs-4 text-center">
                            <i class="zmdi zmdi-accounts-outline stat-icon"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- المنشورات --}}
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('posts.index') }}" class="dashboard-card-link">
                <div class="card-box stat-card">
                    <div class="row">
                        <div class="col-xs-8">
                            <div class="stat-title">المنشورات</div>
                            <div class="stat-value">
                                {{ \App\Models\Post::whereType('post')->count() }}
                            </div>
                            <div class="stat-desc">إجمالي المنشورات</div>
                        </div>
                        <div class="col-xs-4 text-center">
                            <i class="zmdi zmdi-collection-text stat-icon"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- الوظائف --}}
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('jobs.index') }}" class="dashboard-card-link">
                <div class="card-box stat-card">
                    <div class="row">
                        <div class="col-xs-8">
                            <div class="stat-title">الوظائف</div>
                            <div class="stat-value">
                                {{ \App\Models\Post::whereType('job')->count() }}
                            </div>
                            <div class="stat-desc">إجمالي الوظائف</div>
                        </div>
                        <div class="col-xs-4 text-center">
                            <i class="zmdi zmdi-case stat-icon"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- الإعلانات المدفوعة --}}
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('sponsors.index') }}" class="dashboard-card-link">
                <div class="card-box stat-card">
                    <div class="row">
                        <div class="col-xs-8">
                            <div class="stat-title">الإعلانات المدفوعة</div>
                            <div class="stat-value">
                                {{ \App\Models\Sponsor::whereType('paid')->count() }}
                            </div>
                            <div class="stat-desc">إجمالي الإعلانات المدفوعة</div>
                        </div>
                        <div class="col-xs-4 text-center">
                            <i class="zmdi zmdi-money stat-icon"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- الإعلانات المجانية --}}
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('sponsors.index') }}" class="dashboard-card-link">
                <div class="card-box stat-card">
                    <div class="row">
                        <div class="col-xs-8">
                            <div class="stat-title">الإعلانات المجانية</div>
                            <div class="stat-value">
                                {{ \App\Models\Sponsor::whereType('free')->count() }}
                            </div>
                            <div class="stat-desc">إجمالي الإعلانات المجانية</div>
                        </div>
                        <div class="col-xs-4 text-center">
                            <i class="zmdi zmdi-flag stat-icon"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

    </div>
@endsection
