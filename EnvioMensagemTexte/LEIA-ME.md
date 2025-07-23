###
# Passo 1: Configurar o Contexto PJSIP
    Acesse o IssabelPBX

    Vá em Configurações SIP > Configurações PJSIP

    Localize a opção Contexto de Mensagens

    Defina como: textmessages (ou o nome que preferir)

# Psso 2: Criar Contexto Personalizado
    Edite o arquivo /etc/asterisk/extensions_custom.conf

    Adicione este código:

[textmessages]
exten => _.,1,Gosub(send-text,s,1,(${EXTEN}))
exten => e,1,Hangup()

[send-text]
exten => s,1,NoOp(Sending Text To: ${ARG1})
exten => s,n,Set(PEER=${CUT(CUT(CUT(MESSAGE(from),@,1),<,2),:,2)})
exten => s,n,Set(FROM=${DB(AMPUSER/${PEER}/cidname)})
exten => s,n,Set(CALLERID_NUM=${DB(AMPUSER/${PEER}/cidnum)})
exten => s,n,Set(FROM_SIP=${STRREPLACE(MESSAGE(from),<sip:${PEER}@,<sip:${CALLERID_NUM}@)})
exten => s,n,MessageSend(pjsip:${ARG1},${FROM_SIP})
exten => s,n,Hangup()

# Passo 3: Recarregar Configurações
    No terminal do Issabel, execute:

    asterisk -rx "dialplan reload"

✅ Recomendações:
    Mantenha mensagens abaixo de 1.000 caracteres se quiser garantir compatibilidade.

