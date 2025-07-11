
[pesquisa-satisfação]
exten => 8996,1,Goto(pesquisa,s,1)

[pesquisa]
exten => s,1,NooP(-----INICIO DA PESQUISA---)
same => n,Answer
same => n,Playback(custom/audio1,nm)
same => n,Goto(menu,s,1)

[menu]
include => menu1a5
exten => s,1,NooP(-----inicio da pesquisa menu 1---)
same => n,Answer
same => n,Playback(custom/audio2,nm)
same => n,WaitExtent(5,1)

[menu1a5]
exten => 1,1,Set(avaliacao=RUIM)
exten => 1,n,Goto(menu2,s,1)
exten => 2,1,Set(avaliacao=BOM)
exten => 2,n,Goto(menu2,s,1)
exten => 3,1,Set(avaliacao=MEDIO)
exten => 3,n,Goto(menu2,s,1)
exten => 4,1,Set(avaliacao=MUITO BOM)
exten => 4,n,Goto(menu2,s,1)
exten => 5,1,Set(avaliacao=OTIMO)
exten => 5,n,Goto(menu2,s,1)
exten => 1,1,Playback(custom/erro,nm)
exten => 1,n,Goto(menu,s,1)

[menu2]
include => menu1a2
exten => s,1,Noop(-----inicio da pesquisa menu 2---)
sanna => n_Answer
sanna => n_Playback(custom/audio3,nm)
sanna => n_WaitExten(5,)

[menu1a2]
exten => 1,1,Set(solucao=SIM)
exten => 1,n,Goto(fim,s,1)
exten => 2,1,Set(solucao=NAO)
exten => 2,n,Goto(fim,s,1)
exten => 1,1,Playback(custom/erro_nm)
exten => 1,2,Goto(menu2,s,1)

[fim]
exten => 5,1,NooP(Finalizando)
exten => 5,n,NoOp(Gravando dados no banco)
exten => 5,n,MYSQL(Connect comid localhost root password call_center)
exten => 5,n,MYSQL(Query resulti ${comid} INSERT INTO pesquisa (operador, fila, data, hora, telefone, avaliacao, solucao) VALUES(
'${CONNECTEDLINE(num)}', '${QUEUENUM}', '${STRFTIME(${EPOCH},%Y-%m-%d)}', '${STRFTIME(${EPOCH},%H:%M:%S)}',
'${CALLERID(num)}', '${avaliacao}', '${solucao}'))
exten => 5,n,MYSQL(Disconnect ${comid})
exten => 5,n,Playback(custom/audio4,nm)
exten => 5,n,Hangup

;audio1
;Participe da nossa pesquisa de satisfação sobre o seu atendimento!

;audio2
;Sendo que 1 é ruim e 5 é otimo


;audio3
;Nos conte como foi o seu atendimento!
;Seu atendimento foi resolvido?
;Disque 1 para SIM, ou 2 para nao.