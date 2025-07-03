@extends('email.main')

@section('title', 'We Upgraded Your Account')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="content">

                <!-- Header Message -->
                <h1 class="greeting">🎉 Your Account Has Been Upgraded!</h1>

                <p class="message">
                    Hello {{ $user->first_name ?? 'there' }},<br><br>

                    Congratulations! We've successfully upgraded your account to <strong>{{ucfirst($tier)}} Level</strong>.
                    You now have access to enhanced features and higher limits.
                </p>

            </td>
        </tr>
    </table>
@endsection
