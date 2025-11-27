@extends('voyager::master')

@section('content')
@stop

@section('javascript')
    <link rel="stylesheet"
        href="https://www.gstatic.com/dialogflow-console/fast/df-messenger/prod/v1/themes/df-messenger-default.css">
    <script src="https://www.gstatic.com/dialogflow-console/fast/df-messenger/prod/v1/df-messenger.js"></script>
    <df-messenger project-id="gen-lang-client-0011860561" agent-id="6948b41c-cab5-434b-8b10-089ecfd4a73d" language-code="fr"
        max-query-length="-1">
        <df-messenger-chat chat-title="Oscar par OrthoMaster"
            chat-title-icon="https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/web/logo_orange_no_bg_40.webp">
        </df-messenger-chat>
    </df-messenger>

    <style>
        :root {
            --df-messenger-focus-color: #eb7b0f;
            --df-messenger-default-focus-color: #eb7b0f;
            --df-messenger-focus-color-contrast: #eb7b0f;
            --df-messenger-primary-color: #eb7b0f;
        }

        df-messenger {
            z-index: 999;
            position: fixed;

            --df-messenger-font-color: #1f2937;
            --df-messenger-font-family: "Open Sans";
            --df-messenger-titlebar-font-color: #eb7b0f;
            --df-messenger-chat-background: #f9fafb;

            --df-messenger-message-user-background: #eb7b0f;
            --df-messenger-message-user-font-color: #fff;

            --df-messenger-message-bot-background: #fff;
            --df-messenger-message-bot-font-color: #1f2937;
            --df-messenger-message-bot-border: 1px solid #d1d5db;

            bottom: 0;
            right: 0;
            top: 0;
            width: 650px;
        }

        @media (max-width: 425px) {
            df-messenger {
                width: 100%;
            }
        }

        @media (min-width: 1024px) {
            df-messenger {
                width: 70%;
            }
        }
    </style>
@stop
