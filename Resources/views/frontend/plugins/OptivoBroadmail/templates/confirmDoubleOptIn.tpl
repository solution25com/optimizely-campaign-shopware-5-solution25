{extends file="parent:frontend/account/index.tpl"}

{block name='frontend_index_content'}
    <div>
        {if $recipientSuccessfulCreated}
            {include file="frontend/_includes/messages.tpl" type='success' content="{s namespace="frontend/plugins/OptivoBroadmail" name="RecipientSuccessfulCreated"}Sie haben die Registrierung bestätigt{/s}"}
        {/if}
        {if $recipientAlreadyRegistered}
            {include file="frontend/_includes/messages.tpl" type='error' content="{s namespace="frontend/plugins/OptivoBroadmail" name="RecipientAlreadyRegistered"}Sie haben die Registrierung schon bestätigt{/s}"}
        {/if}
        {if $recipientWrongData}
            {include file="frontend/_includes/messages.tpl" type='error' content="{s namespace="frontend/plugins/OptivoBroadmail" name="RecipientWrongData"}Fehler: falsche Daten.{/s}"}
        {/if}
        {if $recipientNoConfirmationHash}
            {include file="frontend/_includes/messages.tpl" type='error' content="{s namespace="frontend/plugins/OptivoBroadmail" name="RecipientNoConfirmationHash"}Der Hash existiert nicht mehr. Haben Sie die Registrierung schon bestätigt?{/s}"}
        {/if}
    </div>
{/block}