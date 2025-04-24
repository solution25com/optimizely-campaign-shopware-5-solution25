{extends file='parent:frontend/newsletter/index.tpl'}


{* Error messages *}
{block name="frontend_newsletter_error_messages"}
<div class="newsletter--error-messages">
    {if $newsletterRegisterError == 'duplicate'}
        {if $sUserLoggedIn}
            {include file="frontend/_includes/messages.tpl" type='error' content="{s name="DuplicateEmail" namespace="frontend/plugins/OptivoBroadmail"}Sie sind schon angemeldet{/s}"}
        {else}
            {include file="frontend/_includes/messages.tpl" type='success' content="{s name="PleaseConfirmYourRegister" namespace="frontend/plugins/OptivoBroadmail"}Vielen Dank. Wir haben Ihnen eine Bestätigungsemail gesendet. Klicken Sie auf den enthaltenen Link um Ihre Anmeldung zu bestätigen.{/s}"}
        {/if}
    {/if}

    {* Display the message for first-time registration when attempting to register the same email a second time (for privacy) *}
    {if $newsletterRegisterDoubleOptinSuccessful || $sStatus.code == 2}
        {include file="frontend/_includes/messages.tpl" type='success' content="{s name="PleaseConfirmYourRegister" namespace="frontend/plugins/OptivoBroadmail"}Vielen Dank. Wir haben Ihnen eine Bestätigungsemail gesendet. Klicken Sie auf den enthaltenen Link um Ihre Anmeldung zu bestätigen.{/s}"}
    {/if}

    {if $unsubscribeSuccessful}
        {include file="frontend/_includes/messages.tpl" type='success' content="{s name="YouWasSuccessfullyUnsubscribed" namespace="frontend/plugins/OptivoBroadmail"}Sie wurden erfolgreich abgemeldet{/s}"}
    {/if}
</div>
{/block}