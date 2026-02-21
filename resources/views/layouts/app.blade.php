<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">

    {{-- NAVBAR mobile only --}}
    <x-nav sticky class="lg:hidden">
        <x-slot:brand>
            <x-app-brand />
        </x-slot:brand>
        <x-slot:actions>
            <label for="main-drawer" class="lg:hidden me-3">
                <x-icon name="o-bars-3" class="cursor-pointer" />
            </label>
        </x-slot:actions>
    </x-nav>

    {{-- MAIN --}}
    <x-main>
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

            {{-- BRAND --}}
            <x-app-brand class="px-5 pt-4" />

            {{-- MENU --}}
            <x-menu activate-by-route>

                {{-- User --}}
                @if($user = auth()->user())
                    <x-menu-separator />

                    <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 !-my-2 rounded">
                        <x-slot:actions>
                            <x-button icon="o-power" class="btn-circle btn-ghost btn-xs" tooltip-left="logoff" no-wire-navigate link="/logout" />
                        </x-slot:actions>
                    </x-list-item>
                @endif

                <x-menu-separator />
                <x-menu-item title="Dashboard" icon="o-home" link="/dashboard" />

                {{-- STUDENT --}}
                @role('student')
                    <x-menu-separator title="Student Page" />
                    <x-menu-item title="Profile" icon="o-user" link="{{ route('student.profile') }}" />
                    <x-menu-item title="Research" icon="o-academic-cap" link="{{ route('student.research') }}" />
                    <x-menu-item title="List of Event" icon="o-calendar-days" link="#" />
                    <x-menu-item title="Telegram" icon="o-paper-airplane" link="#" />
                @endrole

                {{-- STAFF --}}
                @role('staff')
                    <x-menu-separator title="Staff" />
                    <x-menu-sub title="Research" icon="o-academic-cap">
                        <x-menu-item title="Supervise" link="#" />
                        <x-menu-item title="Review" link="#" />
                    </x-menu-sub>
                    <x-menu-sub title="Event" icon="o-calendar-days">
                        <x-menu-item title="List of Event" link="#" />
                        <x-menu-item title="Defense" link="#" />
                        <x-menu-item title="Final Defense" link="#" />
                        <x-menu-item title="Seminar" link="#" />
                    </x-menu-sub>
                    <x-menu-item title="Telegram" icon="o-paper-airplane" link="#" />
                    <x-menu-item title="Mobile Activation" icon="o-device-phone-mobile" link="#" />
                @endrole

                {{-- SPECIALIZATION COORDINATOR --}}
                @hasanyrole('research|defense')
                    <x-menu-separator title="Specialization Coordinator" />
                    @role('research')
                        <x-menu-sub title="Students' Research" icon="o-academic-cap">
                            <x-menu-item title="New and Renew" link="#" />
                            <x-menu-item title="Being review" link="#" />
                            <x-menu-item title="In progress" link="#" />
                            <x-menu-item title="Rejected" link="#" />
                            <x-menu-item title="Login As" link="{{ route('specialization.login-as') }}" />
                        </x-menu-sub>
                    @endrole
                    @role('defense')
                        <x-menu-sub title="Event" icon="o-calendar-days">
                            <x-menu-item title="List of Event" link="#" />
                            <x-menu-item title="Applicant of defense" link="#" />
                            <x-menu-item title="Applicant of Final defense" link="#" />
                            <x-menu-item title="Applicant of Seminar" link="#" />
                        </x-menu-sub>
                    @endrole
                @endhasanyrole

                {{-- ADMIN --}}
                @role('admin')
                    <x-menu-separator title="Admin Page" />
                    <x-menu-sub title="User Management" icon="o-users">
                        <x-menu-item title="Staff" link="#" />
                        <x-menu-item title="Student" link="#" />
                        <x-menu-item title="Login as program" link="#" />
                    </x-menu-sub>
                    <x-menu-sub title="Config" icon="o-cog-6-tooth">
                        <x-menu-item title="Research" link="#" />
                        <x-menu-item title="Institution" link="#" />
                    </x-menu-sub>
                @endrole

                {{-- SUPER ADMIN --}}
                @role('super_admin')
                    <x-menu-separator title="Super Admin" />
                    <x-menu-sub title="Config-SA" icon="o-cog-6-tooth">
                        <x-menu-item title="Program Study" link="#" />
                        <x-menu-item title="Telegram" link="#" />
                    </x-menu-sub>
                    <x-menu-sub title="User Management" icon="o-users">
                        <x-menu-item title="Staff" link="#" />
                    </x-menu-sub>
                @endrole

                {{-- OPERATOR --}}
                @role('operator')
                    <x-menu-separator title="Operator" />
                     <x-menu-sub title="Approval of research" icon="o-check-circle">
                        <x-menu-item title="SIAS Proposal" link="#" />
                    </x-menu-sub>
                @endrole

                {{-- PROGRAM --}}
                @role('program')
                    <x-menu-separator title="Program" />
                    <x-menu-item title="Mark of Pre-defense" link="#" />
                    <x-menu-item title="Approval of Final defense" link="#" />
                    <x-menu-item title="Mark of Final defense" link="#" />
                @endrole

            </x-menu>
        </x-slot:sidebar>

        {{-- The `$slot` goes here --}}
        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-main>

    {{--  TOAST area --}}
    <x-toast />
</body>
</html>
