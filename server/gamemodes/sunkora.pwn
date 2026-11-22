// SUNKORA ROLEPLAY: original minimal development game mode (not the full RP mod).
#pragma semicolon 1
#pragma rational Float
#define SUNKORA_MAX_PLAYERS 1000
#define COLOR_WHITE 0xEEEEEEFF
#define COLOR_ACCENT 0xC4A2FFFF
#define COLOR_HINT 0xAABBD0FF

native SetGameModeText(const text[]);
native AddPlayerClass(modelid, Float:x, Float:y, Float:z, Float:angle, w1, a1, w2, a2, w3, a3);
native SendClientMessage(playerid, color, const message[]);
native GetPlayerName(playerid, name[], len);
native SetPlayerInterior(playerid, interior);
native SetPlayerVirtualWorld(playerid, world);
native SetPlayerPos(playerid, Float:x, Float:y, Float:z);
native GetPlayerPos(playerid, &Float:x, &Float:y, &Float:z);
native Float:GetPlayerDistanceFromPoint(playerid, Float:x, Float:y, Float:z);
native GetPlayerInterior(playerid);
native GetPlayerVirtualWorld(playerid);
native IsPlayerConnected(playerid);
native strcat(dest[], const source[], maxlen);
native format(output[], len, const fmt[], {Float,_}:...);

forward OnGameModeInit();
forward OnPlayerConnect(playerid);
forward OnPlayerDisconnect(playerid, reason);
forward OnPlayerText(playerid, text[]);
forward OnPlayerCommandText(playerid, cmdtext[]);

new gMessages[SUNKORA_MAX_PLAYERS];
main() {}

stock Exact(const a[], const b[])
{
    new i = 0;
    while (a[i] != 0 && b[i] != 0)
    {
        if (a[i] != b[i]) return 0;
        i++;
    }
    return a[i] == 0 && b[i] == 0;
}
stock HasArgs(const a[], const b[])
{
    new i = 0;
    while (b[i] != 0)
    {
        if (a[i] != b[i]) return 0;
        i++;
    }
    return a[i] == 32 && a[i+1] != 0;
}
stock LocalMessage(playerid, color, const message[])
{
    new Float:x, Float:y, Float:z;
    if (!GetPlayerPos(playerid, x, y, z)) return 0;
    new interior=GetPlayerInterior(playerid);
    new world=GetPlayerVirtualWorld(playerid);
    for (new p=0; p<SUNKORA_MAX_PLAYERS; p++)
    {
        if (!IsPlayerConnected(p)) continue;
        if (GetPlayerInterior(p) != interior || GetPlayerVirtualWorld(p) != world) continue;
        if (GetPlayerDistanceFromPoint(p, x, y, z) <= 25.0)
            SendClientMessage(p, color, message);
    }
    return 1;
}
stock RoleplayAction(playerid, const cmd[], offset, const prefix[])
{
    new name[25], text[121], output[180], i=0;
    while (cmd[offset] != 0 && i<120)
    {
        text[i++]=cmd[offset++];
    }
    text[i]=0;
    if (i==0) return 0;
    GetPlayerName(playerid, name, sizeof(name));
    output[0]=0;
    strcat(output,prefix,sizeof(output));
    strcat(output,name,sizeof(output));
    strcat(output,": ",sizeof(output));
    strcat(output,text,sizeof(output));
    return LocalMessage(playerid,COLOR_ACCENT,output);
}
public OnGameModeInit()
{
    SetGameModeText("SUNKORA | Development");
    AddPlayerClass(7,1742.0,-1862.0,13.5,90.0,0,0,0,0,0,0);
    return 1;
}
public OnPlayerConnect(playerid)
{
    gMessages[playerid]=0;
    SendClientMessage(playerid,COLOR_ACCENT,"Welcome to SUNKORA test server.");
    SendClientMessage(playerid,COLOR_HINT,"Try /help for available commands.");
    return 1;
}
public OnPlayerDisconnect(playerid, reason)
{
    gMessages[playerid]=0;
    return 1;
}
public OnPlayerText(playerid, text[])
{
    new name[25], output[180];
    GetPlayerName(playerid,name,sizeof(name));
    output[0]=0;
    strcat(output,name,sizeof(output));
    strcat(output,": ",sizeof(output));
    strcat(output,text,sizeof(output));
    LocalMessage(playerid,COLOR_WHITE,output);
    gMessages[playerid]++;
    return 0;
}
public OnPlayerCommandText(playerid, cmdtext[])
{
    if (Exact(cmdtext,"/help"))
    {
        SendClientMessage(playerid,COLOR_ACCENT,"/help /rules /spawn /stats /me /do /b");
        SendClientMessage(playerid,COLOR_HINT,"Local chat radius: 25m; no persistent accounts yet.");
        return 1;
    }
    if (Exact(cmdtext,"/rules"))
    {
        SendClientMessage(playerid,COLOR_WHITE,"Respect others. No exploits or cheats.");
        SendClientMessage(playerid,COLOR_HINT,"Final RP rules will be announced before release.");
        return 1;
    }
    if (Exact(cmdtext,"/spawn"))
    {
        SetPlayerInterior(playerid,0);
        SetPlayerVirtualWorld(playerid,0);
        SetPlayerPos(playerid,1742.0,-1862.0,13.5);
        return 1;
    }
    if (Exact(cmdtext,"/stats"))
    {
        new text[85];
        format(text,sizeof(text),"Chat messages this session: %d",gMessages[playerid]);
        SendClientMessage(playerid,COLOR_WHITE,text);
        return 1;
    }
    if (HasArgs(cmdtext,"/me")) { RoleplayAction(playerid,cmdtext,4,"* "); return 1; }
    if (HasArgs(cmdtext,"/do")) { RoleplayAction(playerid,cmdtext,4,"* "); return 1; }
    if (HasArgs(cmdtext,"/b")) { RoleplayAction(playerid,cmdtext,3,"(( OOC: "); return 1; }
    return 0;
}
