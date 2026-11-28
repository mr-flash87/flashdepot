package com.flashpay.gateway

import android.Manifest
import android.annotation.SuppressLint
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.PowerManager
import android.provider.Settings
import android.provider.Telephony
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.BadgedBox
import androidx.compose.material3.Badge
import androidx.compose.material.icons.filled.Send
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.Home
import androidx.compose.foundation.clickable
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.MoreVert
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/** Photo de l'état de la passerelle, rafraîchie toutes les 2 secondes. */
data class UiState(
    val paired: Boolean = false,
    val deviceName: String = "",
    val serverUrl: String = "",
    val lastSync: Long = 0,
    val lastError: String = "",
    val pending: Long = 0,
    val sentToday: Int = 0,
    val matchedToday: Int = 0,
    val smsPermission: Boolean = false,
    val notifPermission: Boolean = true,
    val batteryOk: Boolean = false,
    val serviceOn: Boolean = false,
    val entries: List<LogEntry> = emptyList(),
)

class MainActivity : ComponentActivity() {
    lateinit var store: Store

    override fun onCreate(savedInstanceState: Bundle?) {
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)
        store = Store.get(this)
        if (store.isPaired) {
            GatewayService.start(this)
            Sync.schedulePeriodic(this)
        }
        setContent { GatewayTheme { GatewayApp(this) } }
    }

    fun has(p: String) = checkSelfPermission(p) == PackageManager.PERMISSION_GRANTED

    fun readState(): UiState {
        val pm = getSystemService(POWER_SERVICE) as PowerManager
        return UiState(
            paired = store.isPaired,
            deviceName = store.deviceName,
            serverUrl = store.serverUrl,
            lastSync = store.lastSyncAt,
            lastError = store.lastError,
            pending = store.pendingCount(),
            sentToday = store.today("sent"),
            matchedToday = store.today("matched"),
            smsPermission = has(Manifest.permission.RECEIVE_SMS),
            notifPermission = Build.VERSION.SDK_INT < 33 || has(Manifest.permission.POST_NOTIFICATIONS),
            batteryOk = pm.isIgnoringBatteryOptimizations(packageName),
            serviceOn = GatewayService.running,
            entries = store.recentEntries(40),
        )
    }

    fun neededPermissions(): Array<String> {
        val wanted = mutableListOf(Manifest.permission.RECEIVE_SMS, Manifest.permission.READ_SMS)
        if (Build.VERSION.SDK_INT >= 33) wanted += Manifest.permission.POST_NOTIFICATIONS
        return wanted.filterNot { has(it) }.toTypedArray()
    }

    @SuppressLint("BatteryLife")
    fun openBatterySettings() {
        try {
            startActivity(Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS, Uri.parse("package:$packageName")))
        } catch (e: Exception) {
            startActivity(Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS))
        }
    }

    fun openAppSettings() {
        startActivity(Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:$packageName")))
    }

    fun startGateway() {
        GatewayService.start(this)
        Sync.schedulePeriodic(this)
    }

    fun unpair() {
        store.token = ""
        stopService(Intent(this, GatewayService::class.java))
        store.log("Téléphone dissocié")
    }

    /** Remet en file les SMS des dernières 24 h (le serveur ignore les doublons). Appel hors thread principal. */
    fun requeueInbox(): Int {
        if (!has(Manifest.permission.READ_SMS)) return -1
        var count = 0
        val since = System.currentTimeMillis() - 24 * 3600 * 1000L
        contentResolver.query(
            Telephony.Sms.Inbox.CONTENT_URI,
            arrayOf(Telephony.Sms.ADDRESS, Telephony.Sms.BODY, Telephony.Sms.DATE, Telephony.Sms.DATE_SENT),
            "${Telephony.Sms.DATE} > ?", arrayOf(since.toString()), "${Telephony.Sms.DATE} ASC"
        )?.use { c ->
            while (c.moveToNext()) {
                val sender = c.getString(0) ?: continue
                val body = c.getString(1) ?: continue
                val ts = c.getLong(3).takeIf { it > 0 } ?: c.getLong(2)
                if (store.isAllowedSender(sender) && store.enqueue(sender, body, ts)) count++
            }
        }
        store.log("📤 $count SMS des dernières 24 h remis en file")
        Sync.now(this)
        return count
    }
}

/* =========================================================================================
 *  Écrans
 * ========================================================================================= */

@Composable
fun GatewayApp(activity: MainActivity) {
    var state by remember { mutableStateOf(activity.readState()) }
    val snackbar = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()

    val permLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) {
        state = activity.readState()
        if (state.paired) activity.startGateway()
    }
    LaunchedEffect(Unit) {
        val missing = activity.neededPermissions()
        if (missing.isNotEmpty()) permLauncher.launch(missing)
        while (true) {
            state = activity.readState()
            delay(2000)
        }
    }
    val askPermissions = {
        val missing = activity.neededPermissions()
        if (missing.isNotEmpty()) permLauncher.launch(missing) else activity.openAppSettings()
    }
    val toast: (String) -> Unit = { msg -> scope.launch { snackbar.showSnackbar(msg) } }

    Scaffold(
        snackbarHost = { SnackbarHost(snackbar) },
        containerColor = MaterialTheme.colorScheme.background,
    ) { pad ->
        if (!state.paired) {
            PairScreen(activity, state, pad, askPermissions, toast) { state = activity.readState() }
        } else {
            HomeScreen(activity, state, pad, askPermissions, toast) { state = activity.readState() }
        }
    }
}

@Composable
private fun HeaderBand(title: String, subtitle: String, trailing: @Composable () -> Unit = {}) {
    Box(
        Modifier
            .fillMaxWidth()
            .background(Brush.linearGradient(listOf(Brand.Orange2, Brand.Orange, Brand.OrangeDeep)))
            .statusBarsPadding()
            .padding(start = 20.dp, end = 8.dp, top = 14.dp, bottom = 26.dp)
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier.size(46.dp).clip(RoundedCornerShape(14.dp)).background(Color.White.copy(alpha = 0.2f)),
                contentAlignment = Alignment.Center
            ) { Text("⇅", color = Color.White, fontWeight = FontWeight.Black, fontSize = 24.sp) }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(title, color = Color.White, fontWeight = FontWeight.Bold, fontSize = 19.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(subtitle, color = Color.White.copy(alpha = 0.72f), fontSize = 13.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            trailing()
        }
    }
}

@Composable
private fun SectionCard(modifier: Modifier = Modifier, content: @Composable () -> Unit) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        border = androidx.compose.foundation.BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) { Column(Modifier.padding(20.dp)) { content() } }
}

@Composable
private fun CardTitle(text: String) {
    Text(text, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = MaterialTheme.colorScheme.onSurface)
    Spacer(Modifier.height(12.dp))
}

/* ---------------------------------- Association ---------------------------------- */

@Composable
fun PairScreen(
    activity: MainActivity, state: UiState, pad: PaddingValues,
    askPermissions: () -> Unit, toast: (String) -> Unit, refresh: () -> Unit,
) {
    var url by remember { mutableStateOf(activity.store.serverUrl) }
    var code by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf("") }
    val scope = rememberCoroutineScope()

    LazyColumn(
        Modifier.fillMaxSize().padding(bottom = pad.calculateBottomPadding()),
        contentPadding = PaddingValues(bottom = 32.dp),
    ) {
        item { HeaderBand("FlashDépôt", "Suivi automatique des dépôts et remboursements") }
        item {
            Column(Modifier.padding(horizontal = 16.dp).padding(top = 16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                SectionCard {
                    Text("Associer ce téléphone", fontWeight = FontWeight.Bold, fontSize = 20.sp)
                    Spacer(Modifier.height(6.dp))
                    Text(
                        "Sur le site FlashDépôt, ouvrez Téléphone → Générer un code : un code à 6 chiffres s'affiche.",
                        color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 14.sp
                    )
                    Spacer(Modifier.height(16.dp))
                    OutlinedTextField(
                        value = url, onValueChange = { url = it; error = "" },
                        label = { Text("Adresse du site") },
                        placeholder = { Text("https://depot.monsite.com") },
                        singleLine = true, modifier = Modifier.fillMaxWidth(),
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
                        shape = MaterialTheme.shapes.medium,
                    )
                    Spacer(Modifier.height(12.dp))
                    OutlinedTextField(
                        value = code,
                        onValueChange = { v -> code = v.filter { it.isDigit() }.take(6); error = "" },
                        label = { Text("Code d'association") },
                        placeholder = { Text("000000") },
                        singleLine = true, modifier = Modifier.fillMaxWidth(),
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
                        textStyle = MaterialTheme.typography.headlineSmall.copy(
                            fontFamily = FontFamily.Monospace, letterSpacing = 8.sp, textAlign = TextAlign.Center
                        ),
                        shape = MaterialTheme.shapes.medium,
                    )
                    if (error.isNotEmpty()) {
                        Spacer(Modifier.height(10.dp))
                        Notice(error, Brand.Red, Brand.RedSoft, Icons.Filled.Warning)
                    }
                    Spacer(Modifier.height(16.dp))
                    Button(
                        onClick = {
                            var u = url.trim()
                            if (u.isEmpty() || code.length != 6) {
                                error = "Saisissez l'adresse du site et le code à 6 chiffres."
                                return@Button
                            }
                            if (!u.startsWith("http")) u = "https://$u"
                            loading = true
                            scope.launch {
                                try {
                                    withContext(Dispatchers.IO) { Api.pair(activity.store, u, code) }
                                    activity.store.lastError = ""
                                    activity.store.log("🔗 Associé au serveur $u")
                                    activity.startGateway()
                                    toast("Téléphone associé ✅")
                                    refresh()
                                } catch (e: Exception) {
                                    error = e.message ?: "Échec de l'association"
                                } finally {
                                    loading = false
                                }
                            }
                        },
                        enabled = !loading,
                        modifier = Modifier.fillMaxWidth().height(54.dp),
                        shape = MaterialTheme.shapes.medium,
                    ) {
                        if (loading) CircularProgressIndicator(Modifier.size(22.dp), color = Color.White, strokeWidth = 2.dp)
                        else Text("Associer ce téléphone", fontWeight = FontWeight.SemiBold, fontSize = 16.sp)
                    }
                }
                ChecklistCard(activity, state, askPermissions)
                PrivacyNote()
            }
        }
    }
}

/* ---------------------------------- Accueil ---------------------------------- */

@Composable
fun HomeScreen(
    activity: MainActivity, state: UiState, pad: PaddingValues,
    askPermissions: () -> Unit, toast: (String) -> Unit, refresh: () -> Unit,
) {
    val scope = rememberCoroutineScope()
    var tab by remember { mutableStateOf(0) }
    var syncing by remember { mutableStateOf(false) }
    var confirmUnpair by remember { mutableStateOf(false) }

    val syncNow: () -> Unit = {
        syncing = true
        scope.launch {
            try {
                val n = withContext(Dispatchers.IO) {
                    val sent = Api.flush(activity, activity.store)
                    Api.heartbeat(activity, activity.store)
                    sent
                }
                activity.store.lastError = ""
                toast(if (n > 0) "Connexion OK · $n SMS envoyés" else "Connexion OK ✅")
            } catch (e: Exception) {
                activity.store.lastError = e.message ?: "Erreur réseau"
                toast("Erreur : ${e.message}")
            } finally {
                syncing = false
                refresh()
            }
        }
    }
    val requeue: () -> Unit = {
        scope.launch {
            val n = withContext(Dispatchers.IO) { activity.requeueInbox() }
            toast(if (n < 0) "Autorisez d'abord la lecture des SMS" else "$n SMS remis en file (les doublons sont ignorés)")
            refresh()
        }
    }
    val todo = listOf(state.smsPermission, state.notifPermission, state.batteryOk, state.serviceOn).count { !it }
    val titles = listOf("Tableau de bord", "", "Journal", "Réglages")

    Column(Modifier.fillMaxSize().padding(bottom = pad.calculateBottomPadding())) {
        LazyColumn(Modifier.weight(1f), contentPadding = PaddingValues(bottom = 24.dp)) {
            item {
                TopBar(titles[tab], state.deviceName.ifEmpty { hostOf(state.serverUrl) }) {
                    RoundIcon(onClick = syncNow, enabled = !syncing) {
                        if (syncing) CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp)
                        else Icon(Icons.Filled.Refresh, "Synchroniser", tint = MaterialTheme.colorScheme.onSurface)
                    }
                }
            }
            item {
                Column(Modifier.padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                    when (tab) {
                        0 -> {
                            StatusCard(state)
                            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                                BigStat("Opérations reconnues", state.matchedToday.toString(), "aujourd'hui", Icons.Filled.CheckCircle, Modifier.weight(1f), Brand.Green)
                                BigStat("SMS transmis", state.sentToday.toString(), "aujourd'hui", Icons.Filled.Email, Modifier.weight(1f))
                            }
                            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                                BigStat("En attente", state.pending.toString(), "d'envoi au site", Icons.Filled.Refresh, Modifier.weight(1f), if (state.pending > 0) Brand.Amber else Brand.Green)
                                BigStat("Dernier contact", if (state.lastSync > 0) relative(state.lastSync) else "—", "avec le site", Icons.Filled.CheckCircle, Modifier.weight(1f))
                            }
                            if (todo > 0) {
                                ActionBanner(
                                    "$todo réglage(s) à faire", "Indispensable pour suivre les opérations automatiquement.",
                                    "Régler"
                                ) { tab = 3 }
                            }
                            ActivityCard(state.entries.take(6), onMore = { tab = 2 })
                        }
                        2 -> ActivityCard(state.entries, onMore = null)
                        else -> {
                            ChecklistCard(activity, state, askPermissions)
                            SectionCard {
                                CardTitle("Ce téléphone")
                                InfoLine("Nom", state.deviceName.ifEmpty { "—" })
                                InfoLine("Site", hostOf(state.serverUrl))
                                InfoLine("Dernier contact", if (state.lastSync > 0) relative(state.lastSync) else "—")
                                InfoLine("Version", BuildConfig.VERSION_NAME)
                                Spacer(Modifier.height(12.dp))
                                OutlinedButton(onClick = syncNow, modifier = Modifier.fillMaxWidth(), shape = MaterialTheme.shapes.medium) { Text("Tester la connexion") }
                                Spacer(Modifier.height(8.dp))
                                OutlinedButton(onClick = requeue, modifier = Modifier.fillMaxWidth(), shape = MaterialTheme.shapes.medium) { Text("Renvoyer les SMS des dernières 24 h") }
                                Spacer(Modifier.height(8.dp))
                                OutlinedButton(onClick = { activity.openAppSettings() }, modifier = Modifier.fillMaxWidth(), shape = MaterialTheme.shapes.medium) { Text("Paramètres Android de l'application") }
                                Spacer(Modifier.height(8.dp))
                                TextButton(onClick = { confirmUnpair = true }, modifier = Modifier.fillMaxWidth()) { Text("Dissocier ce téléphone", color = Brand.Red) }
                            }
                            PrivacyNote()
                        }
                    }
                }
            }
        }
        NavigationBar(containerColor = MaterialTheme.colorScheme.surface, tonalElevation = 0.dp) {
            listOf(
                Triple("Accueil", Icons.Filled.Home, 0),
                Triple("Journal", Icons.Filled.List, 2),
                Triple("Réglages", Icons.Filled.Settings, 3),
            ).forEach { (label, icon, i) ->
                NavigationBarItem(
                    selected = tab == i, onClick = { tab = i },
                    colors = androidx.compose.material3.NavigationBarItemDefaults.colors(
                        selectedIconColor = Brand.Orange,
                        selectedTextColor = MaterialTheme.colorScheme.onSurface,
                        indicatorColor = MaterialTheme.colorScheme.primaryContainer,
                        unselectedIconColor = MaterialTheme.colorScheme.onSurfaceVariant,
                        unselectedTextColor = MaterialTheme.colorScheme.onSurfaceVariant,
                    ),
                    icon = {
                        if (i == 3 && todo > 0) BadgedBox(badge = { Badge { Text(todo.toString()) } }) { Icon(icon, label) }
                        else Icon(icon, label)
                    },
                    label = { Text(label, fontSize = 12.sp) },
                )
            }
        }
    }

    if (confirmUnpair) {
        AlertDialog(
            onDismissRequest = { confirmUnpair = false },
            title = { Text("Dissocier ce téléphone ?") },
            text = { Text("Il n'enverra plus les SMS Orange Money. Il faudra un nouveau code pour le réassocier.") },
            confirmButton = {
                TextButton(onClick = { confirmUnpair = false; activity.unpair(); refresh() }) { Text("Dissocier", color = Brand.Red) }
            },
            dismissButton = { TextButton(onClick = { confirmUnpair = false }) { Text("Annuler") } },
        )
    }
}

/** Logo FlashDépôt : carré orange arrondi avec les flèches dépôt / remboursement. */
@Composable
private fun LogoMark(size: Int = 40) {
    Box(
        Modifier.size(size.dp).clip(RoundedCornerShape((size * 0.3).dp))
            .background(Brush.linearGradient(listOf(Brand.Orange2, Brand.Orange))),
        contentAlignment = Alignment.Center
    ) { Text("⇅", color = Color.White, fontWeight = FontWeight.Black, fontSize = (size * 0.5).sp) }
}

/** Barre du haut claire, grand titre (style tableau de bord). */
@Composable
private fun TopBar(title: String, subtitle: String, actions: @Composable () -> Unit) {
    Column(Modifier.fillMaxWidth().statusBarsPadding().padding(start = 20.dp, end = 16.dp, top = 12.dp, bottom = 16.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            LogoMark(40)
            Spacer(Modifier.width(10.dp))
            Text("FlashDépôt", fontWeight = FontWeight.ExtraBold, fontSize = 21.sp, modifier = Modifier.weight(1f))
            actions()
        }
        Spacer(Modifier.height(18.dp))
        Text(title, fontWeight = FontWeight.ExtraBold, fontSize = 30.sp, color = MaterialTheme.colorScheme.onBackground)
        Text(subtitle, fontSize = 14.sp, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
private fun RoundIcon(onClick: () -> Unit, enabled: Boolean = true, content: @Composable () -> Unit) {
    Box(
        Modifier.size(46.dp).clip(CircleShape).background(MaterialTheme.colorScheme.surface)
            .border(1.dp, MaterialTheme.colorScheme.outline, CircleShape)
            .clickable(enabled = enabled, onClick = onClick),
        contentAlignment = Alignment.Center
    ) { content() }
}

/** Grande carte chiffrée : libellé, gros chiffre, détail. */
@Composable
private fun BigStat(label: String, value: String, sub: String, icon: ImageVector, modifier: Modifier = Modifier, accent: Color? = null) {
    Card(
        modifier = modifier,
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        border = androidx.compose.foundation.BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(label, fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.weight(1f), maxLines = 2)
                val chip = when (accent) { Brand.Green -> Brand.GreenSoft; Brand.Amber -> Brand.AmberSoft; Brand.Red -> Brand.RedSoft; else -> Brand.OrangeSoft }
                Box(Modifier.size(38.dp).clip(CircleShape).background(chip), contentAlignment = Alignment.Center) {
                    Icon(icon, null, tint = accent ?: Brand.Orange, modifier = Modifier.size(19.dp))
                }
            }
            Spacer(Modifier.height(10.dp))
            Text(value, fontWeight = FontWeight.ExtraBold, fontSize = 26.sp, color = MaterialTheme.colorScheme.onSurface, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(sub, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1)
        }
    }
}

@Composable
private fun ActionBanner(title: String, sub: String, action: String, onClick: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(20.dp)).background(Brand.AmberSoft).clickable(onClick = onClick).padding(16.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Icon(Icons.Filled.Warning, null, tint = Brand.Amber, modifier = Modifier.size(26.dp))
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(title, fontWeight = FontWeight.Bold, fontSize = 15.sp, color = Brand.Amber)
            Text(sub, fontSize = 12.sp, color = Brand.Amber)
        }
        Text(action, fontWeight = FontWeight.Bold, color = Brand.Amber)
    }
}

@Composable
private fun InfoLine(label: String, value: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = 6.dp)) {
        Text(label, fontSize = 14.sp, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.weight(1f))
        Text(value, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

private fun Modifier.clickableNoRipple(onClick: () -> Unit): Modifier = this.clickable(onClick = onClick)

private enum class Health { OK, WARN, ERROR }

@Composable
private fun StatusCard(state: UiState) {
    val recent = state.lastSync > 0 && System.currentTimeMillis() - state.lastSync < 10 * 60 * 1000
    val health = when {
        !state.smsPermission -> Health.ERROR
        state.lastError.isNotEmpty() -> Health.WARN
        !state.serviceOn || !recent -> Health.WARN
        else -> Health.OK
    }
    val (fg, bg, title) = when (health) {
        Health.OK -> Triple(Brand.Green, Brand.GreenSoft, "Passerelle active")
        Health.WARN -> Triple(Brand.Amber, Brand.AmberSoft, if (state.lastError.isNotEmpty()) "Problème de connexion" else "En attente de connexion")
        Health.ERROR -> Triple(Brand.Red, Brand.RedSoft, "Action requise")
    }
    val detail = when {
        !state.smsPermission -> "Autorisez la réception des SMS pour suivre les opérations."
        state.lastError.isNotEmpty() -> state.lastError
        else -> "Les dépôts et remboursements sont suivis automatiquement."
    }
    val pulse = rememberInfiniteTransition(label = "pulse")
    val a by pulse.animateFloat(0.35f, 1f, infiniteRepeatable(tween(1100), RepeatMode.Reverse), label = "a")

    val gradient = when (health) {
        Health.OK -> listOf(Brand.Orange2, Brand.Orange, Brand.OrangeDeep)
        Health.WARN -> listOf(Color(0xFFE09A1B), Color(0xFFC77700))
        Health.ERROR -> listOf(Color(0xFFE45050), Color(0xFFC62828))
    }
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(26.dp)).background(Brush.linearGradient(gradient)).padding(22.dp)
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(9.dp).alpha(if (health == Health.OK) a else 1f).clip(CircleShape).background(Color.White))
            Spacer(Modifier.width(8.dp))
            Text(title, fontWeight = FontWeight.SemiBold, fontSize = 14.sp, color = Color.White.copy(alpha = 0.92f))
        }
        Spacer(Modifier.height(6.dp))
        Text(
            if (health == Health.OK) "${state.sentToday} SMS" else if (health == Health.ERROR) "À régler" else "Hors ligne",
            fontWeight = FontWeight.ExtraBold, fontSize = 36.sp, color = Color.White
        )
        Text(
            if (health == Health.OK) "transmis aujourd'hui · ${state.matchedToday} opération(s) reconnue(s)" else detail,
            fontSize = 13.sp, color = Color.White.copy(alpha = 0.9f), maxLines = 3, overflow = TextOverflow.Ellipsis
        )
        Spacer(Modifier.height(16.dp))
        Row(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(Color.White.copy(alpha = 0.16f)).padding(horizontal = 14.dp, vertical = 10.dp)
        ) {
            Column(Modifier.weight(1f)) {
                Text("Dernier contact", color = Color.White.copy(alpha = 0.8f), fontSize = 12.sp)
                Text(if (state.lastSync > 0) relative(state.lastSync) else "—", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = Color.White)
            }
            Column(Modifier.weight(1f)) {
                Text("Service", color = Color.White.copy(alpha = 0.8f), fontSize = 12.sp)
                Text(if (state.serviceOn) "En marche" else "Arrêté", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = Color.White)
            }
        }
    }
}

@Composable
private fun InfoCell(label: String, value: String, modifier: Modifier = Modifier) {
    Column(modifier) {
        Text(label, color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)
        Text(value, fontWeight = FontWeight.SemiBold, fontSize = 14.sp, color = MaterialTheme.colorScheme.onSurface)
    }
}

@Composable
private fun StatTile(label: String, value: String, sub: String, modifier: Modifier = Modifier, accent: Color? = null) {
    Card(
        modifier = modifier,
        shape = MaterialTheme.shapes.medium,
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        border = androidx.compose.foundation.BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
    ) {
        Column(Modifier.padding(horizontal = 12.dp, vertical = 14.dp)) {
            Text(value, fontWeight = FontWeight.ExtraBold, fontSize = 24.sp, color = accent ?: MaterialTheme.colorScheme.onSurface)
            Text(label, fontWeight = FontWeight.SemiBold, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurface, maxLines = 1)
            Text(sub, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1)
        }
    }
}

@Composable
private fun ChecklistCard(activity: MainActivity, state: UiState, askPermissions: () -> Unit) {
    SectionCard {
        CardTitle("Réglages du téléphone")
        CheckRow(Icons.Filled.Email, "Réception des SMS", "Indispensable pour lire les confirmations", state.smsPermission, "Autoriser", onClick = askPermissions)
        CheckRow(Icons.Filled.Notifications, "Notifications", "Affiche l'état de la passerelle", state.notifPermission, "Autoriser", onClick = askPermissions)
        CheckRow(Icons.Filled.Lock, "Fonctionnement en continu", "Désactive l'économie de batterie pour l'app", state.batteryOk, "Régler") { activity.openBatterySettings() }
        if (state.paired) {
            CheckRow(Icons.Filled.Settings, "Service de la passerelle", "Tourne en arrière-plan en permanence", state.serviceOn, "Démarrer", last = true) { activity.startGateway() }
        }
        Spacer(Modifier.height(6.dp))
        Notice(
            "Gardez le téléphone branché et connecté à Internet. Sur Tecno, Infinix, itel ou Xiaomi, activez aussi « Démarrage automatique ».",
            MaterialTheme.colorScheme.onSurfaceVariant, MaterialTheme.colorScheme.surfaceVariant, Icons.Filled.Info
        )
    }
}

@Composable
private fun CheckRow(icon: ImageVector, title: String, sub: String, ok: Boolean, action: String, last: Boolean = false, onClick: () -> Unit) {
    Row(Modifier.fillMaxWidth().padding(vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier.size(38.dp).clip(RoundedCornerShape(11.dp)).background(if (ok) Brand.GreenSoft else Brand.AmberSoft),
            contentAlignment = Alignment.Center
        ) { Icon(if (ok) Icons.Filled.CheckCircle else icon, null, tint = if (ok) Brand.Green else Brand.Amber, modifier = Modifier.size(20.dp)) }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(title, fontWeight = FontWeight.SemiBold, fontSize = 14.sp, color = MaterialTheme.colorScheme.onSurface)
            Text(sub, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        if (!ok) {
            OutlinedButton(onClick = onClick, contentPadding = PaddingValues(horizontal = 12.dp, vertical = 4.dp), shape = MaterialTheme.shapes.small) {
                Text(action, fontSize = 13.sp)
            }
        }
    }
}

@Composable
private fun ActivityCard(entries: List<LogEntry>, onMore: (() -> Unit)? = null) {
    SectionCard {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Activité récente", fontWeight = FontWeight.Bold, fontSize = 18.sp, modifier = Modifier.weight(1f))
            if (onMore != null) TextButton(onClick = onMore) { Text("Tout voir") }
        }
        Spacer(Modifier.height(6.dp))
        if (entries.isEmpty()) {
            Text("Aucun événement pour l'instant. Les SMS Orange Money apparaîtront ici.", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 14.sp)
        }
        entries.forEachIndexed { i, e ->
            val (kind, text) = splitEmoji(e.line)
            val color = when (kind) {
                "✅" -> Brand.Green
                "⚠️", "🔎" -> Brand.Amber
                "❌" -> Brand.Red
                "📩", "🔗", "📤" -> Brand.Blue
                else -> MaterialTheme.colorScheme.onSurfaceVariant
            }
            Row(Modifier.fillMaxWidth().padding(vertical = 8.dp), verticalAlignment = Alignment.Top) {
                Box(Modifier.padding(top = 6.dp).size(8.dp).clip(CircleShape).background(color))
                Spacer(Modifier.width(12.dp))
                Text(text, fontSize = 14.sp, color = MaterialTheme.colorScheme.onSurface, modifier = Modifier.weight(1f))
                Spacer(Modifier.width(8.dp))
                Text(time(e.timestamp), fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            if (i < entries.size - 1) HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.6f))
        }
    }
}

@Composable
private fun Notice(text: String, fg: Color, bg: Color, icon: ImageVector) {
    Row(
        Modifier.fillMaxWidth().clip(MaterialTheme.shapes.small).background(bg).padding(12.dp),
        verticalAlignment = Alignment.Top
    ) {
        Icon(icon, null, tint = fg, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(10.dp))
        Text(text, color = fg, fontSize = 13.sp)
    }
}

@Composable
private fun PrivacyNote() {
    Row(Modifier.fillMaxWidth().padding(horizontal = 6.dp), verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Filled.Lock, null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.size(14.dp))
        Spacer(Modifier.width(8.dp))
        Text(
            "Seuls les SMS d'Orange Money sont transmis. Vos SMS personnels ne quittent jamais le téléphone.",
            color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp
        )
    }
}

/* ---------------------------------- Utilitaires ---------------------------------- */

private fun hostOf(url: String): String = try {
    Uri.parse(url).host ?: url
} catch (e: Exception) {
    url
}

private fun time(ts: Long): String {
    val today = SimpleDateFormat("yyyyMMdd", Locale.US).format(Date())
    val day = SimpleDateFormat("yyyyMMdd", Locale.US).format(Date(ts))
    return SimpleDateFormat(if (day == today) "HH:mm" else "dd/MM HH:mm", Locale.FRANCE).format(Date(ts))
}

private fun relative(ts: Long): String {
    val s = (System.currentTimeMillis() - ts) / 1000
    return when {
        s < 60 -> "à l'instant"
        s < 3600 -> "il y a ${s / 60} min"
        s < 86400 -> "il y a ${s / 3600} h"
        else -> SimpleDateFormat("dd/MM HH:mm", Locale.FRANCE).format(Date(ts))
    }
}

/** Sépare l'emoji de tête (type d'événement) du texte. */
private fun splitEmoji(line: String): Pair<String, String> {
    val known = listOf("✅", "⚠️", "🔎", "❌", "📩", "🔗", "📤", "♻️", "ℹ️", "🔄")
    for (k in known) {
        if (line.startsWith(k)) return k to line.removePrefix(k).trim()
    }
    return "" to line
}
