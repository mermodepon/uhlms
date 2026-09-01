# WINDOWS PERFORMANCE AUDIT

Audit date: 2026-08-22 (Asia/Manila)

Scope: read-only performance, process, startup, service, scheduled-task, event-log, device/driver, power, storage, and passive unusual-process inspection. No benchmark or stress test was run. The audit's own temporary PowerShell processes are excluded from the performance assessment.

## Executive summary

The evidence does not point to a failing or continuously saturated SSD, sustained paging, or a processor that is permanently maxed out. During five approximately 15-second samples, total CPU ranged from 18% to 61%, available RAM stayed around 5.0–5.2 GB, disk time was 0–8% with a zero queue, and page activity dropped to zero after the first sample.

The most credible explanation for intermittent visual/UI lag is a display/virtual-display driver problem combined with a heavy interactive workload. Windows recorded a critical user-mode driver crash that took `MermoDisplayAdapter` offline. The Epson Projection Idd Device is currently reported with ConfigManager error code 22 (disabled/error), while Easy&Light Display Hub virtual-display components are present. Separately, Chrome and the Codex/ChatGPT desktop app together used about 3.85 GB of working set across 26 processes, and Dell TechHub/SupportAssist-related processes used about 1.86 GB across 11 processes. That resident footprint can make a 16 GB system feel sluggish even though it was not paging continuously during this audit.

There are also two background reliability problems worth addressing: `IntelTACD` repeatedly failed to start because its file was not found, and Cloudflared terminated/restarted repeatedly during the current boot. Neither was consuming material CPU at the final snapshots, but both can create intermittent background work and indicate inconsistent installed components.

The SSD is reported Healthy/Online and no recent disk, NTFS, NVMe/SATA-controller, WHEA, or display-driver-reset events were found in the targeted seven-day search. One ESENT event did record a 15-second WebCache write, so an isolated storage-latency event cannot be ruled out.

## A. SYSTEM SUMMARY

| Item | Finding |
|---|---|
| Computer | Dell Pro 14 PC14250 |
| Operating system | Windows 11 Pro, version 10.0.26200, build 26200 |
| Last boot | 2026-08-22 20:20:50 +08:00; the initial baseline was approximately 17 minutes after boot |
| CPU | Intel Core 5 220U, 10 cores / 12 logical processors; WMI reported 1,400 MHz current and maximum clock values |
| RAM | 16 GB installed; 15.69 GB visible to Windows |
| GPU | Intel Graphics, driver 32.0.101.7085, status OK; Epson Projection Idd Device status Error; Easy&Light Display HUB virtual display status OK |
| BIOS | Dell Inc. version 1.16.5, 2026-06-16 |
| Physical storage | KIOXIA NVMe EG6 512 GB; Storage module reports SSD/NVMe, Healthy, Online |
| C: | NTFS, 279.6 GB, approximately 38.1 GB free (13.6%) |
| D: | NTFS, 230.7 GB, approximately 101.5 GB free (44.0%), label `Storage` |
| G: | FAT32 Google Drive volume; approximately 36.3 GB free; treated as a mounted/sync volume rather than a separate physical disk |
| Power plan | Balanced |
| Battery observation | Battery WMI status 1, 85% charge, estimated runtime 94 minutes at the time checked; AC comparison was not performed |

### Collection limitations

- The built-in storage reliability-counter query was unavailable to the current CIM client, so temperature, wear, and controller latency counters were not available.
- Built-in Windows queries did not expose a reliable CPU temperature sensor. No temperature or thermal-throttling claim is made.
- GPU engine telemetry was available for a spot check but did not provide a full time series or clean process IDs; one spot check showed roughly 11% 3D engine activity for the Chrome GPU engine and 6% for Desktop Window Manager.
- The explicit initial/maximum pagefile settings were not returned, but the active pagefile was observed at `C:\pagefile.sys` with 2,816 MB allocated.
- A plugged-in versus battery comparison could not be established from one live state.
- Malware screening was passive only. No files were deleted, quarantined, scanned aggressively, or classified as malware based solely on resource use.

## B. CURRENT PERFORMANCE STATE

### Repeated resource samples

Five samples were collected at approximately 15-second intervals from 20:39:43 through 20:40:47.

| Metric | Observed range | Interpretation |
|---|---:|---|
| Total CPU | 18–61% | Variable activity, but no sustained full-CPU condition |
| Available RAM | 5,006–5,191 MB | Approximately one-third of physical RAM remained available |
| Committed memory | 62–64% | Moderate commit use, below an acute commit-limit condition |
| Pages/sec | 0 after the first sample; first sample 38 | Brief initial paging activity, not sustained |
| Page reads/sec | 0 after the first sample; first sample 11 | No continuing read-paging pattern observed |
| Page writes/sec | 0 | No write-paging pressure observed during the sample window |
| Physical disk time | 0–8% | No disk saturation |
| Average disk queue | 0.00 | No queue buildup during the sample window |
| Disk throughput | 0 to approximately 1.75 MB/s | Low overall storage traffic |

The highest interval CPU contributors were Chrome PID 1792 at approximately 3–4% of total CPU capacity and ChatGPT PID 8092 at approximately 3–6% of total CPU capacity. ChatGPT PID 9300 was approximately 3–4% in the intervals where it was active. These are rate samples; cumulative process CPU time was not used as a diagnosis.

At the baseline snapshot there were 279 processes, approximately 127,042 process handles, and approximately 4,001 threads. High handle counts were seen in ordinary multi-process applications: Explorer about 2,955 handles, a Chrome process about 2,234, Dell TechHub instrumentation about 1,876, and a ChatGPT process about 1,823. These counts are notable for tracking but are not, by themselves, evidence of a handle leak.

## C. TOP SUSPECTS

### 1. Display/virtual-display driver instability

**Evidence:** Windows System log critical events 10111 and 10120 on 2026-08-20 reported a user-mode driver crash and `MermoDisplayAdapter` going offline. The Epson Projection Idd Device currently reports ConfigManager error code 22 and status Error. Epson and Easy&Light virtual-display services/devices are installed.

**Severity:** High  
**Confidence:** Medium-High

**Why it could cause lag:** A user-mode display-driver or virtual-display failure can produce intermittent UI freezes, Desktop Window Manager stutter, delayed window redraws, or external-display problems without requiring high overall CPU or disk use.

**Recommended next diagnostic step:** Correlate the user's lag timestamps with System events 10111/10120 and test whether the lag occurs only when the Epson projector or Easy&Light virtual display is connected or active. Have the relevant OEM/vendor driver package repaired or updated through an official source after confirming the exact device in Device Manager.

### 2. Heavy Chrome plus Codex/ChatGPT workload

**Evidence:** 16 Chrome processes and 11 ChatGPT processes used approximately 3.85 GB of working set in aggregate. The repeated samples consistently identified ChatGPT and Chrome renderer/GPU processes as the top user CPU contributors. A spot process-I/O query showed approximately 2.2 MB/s for two ChatGPT child processes while global disk time remained low.

**Severity:** Moderate  
**Confidence:** High for resource contribution; Medium for being the sole cause

**Why it could cause lag:** Multiple Chromium/Electron processes, tabs, extensions, GPU surfaces, and application state consume RAM, threads, handles, and CPU bursts. With other background software present, this can create responsiveness pressure even without sustained paging.

**Recommended next diagnostic step:** During a lag episode, compare responsiveness after closing unused Chrome windows/tabs and extra Codex/ChatGPT windows, then compare the process totals. Review Chrome task-manager tab/extension usage rather than relying on cumulative CPU time.

### 3. Dell TechHub, SupportAssist, and Dell management footprint

**Evidence:** 11 Dell/SupportAssist/ServiceShell processes used approximately 1.86 GB of working set. The largest were Dell TechHub Instrumentation SubAgent (about 313 MB), SupportAssistAgent (273 MB), Dell CoreServices Client (233 MB), Diagnostics SubAgent (198 MB), and Analytics SubAgent (170 MB). CPU was not materially high in the repeated sample, but the resident memory, thread, and handle footprint is substantial.

**Severity:** Moderate  
**Confidence:** Medium

**Why it could cause lag:** These utilities can perform telemetry, diagnostics, update checks, and hardware monitoring. Their resident memory reduces headroom for interactive applications and their background activity may be bursty even when a snapshot shows zero CPU.

**Recommended next diagnostic step:** Identify which Dell features are required, then perform a controlled user-approved comparison of responsiveness with optional Dell monitoring/support components not in active use. Do not disable core Dell or Windows services blindly.

### 4. Broken/stale IntelTACD driver registration

**Evidence:** System log Service Control Manager event 7000 occurred 76 times in the seven-day window, most recently at boot, stating that `IntelTACD` failed because the system could not find the file. The driver is currently stopped with Win32 exit code 2. Intel Dynamic Tuning/Innovation Platform Framework services are otherwise running.

**Severity:** Moderate  
**Confidence:** Medium

**Why it could cause lag:** Repeated start failures can add boot/background retry work and indicate an incomplete Intel platform-driver installation. It may also mean that one part of the intended power/thermal management stack is inconsistent, although this audit did not prove active CPU throttling.

**Recommended next diagnostic step:** Verify the exact Dell/Intel driver package associated with `IntelTACD` and repair it using the Dell model-specific support path. Check whether the event stops after a supported repair; do not delete the service or driver manually.

### 5. Cloudflared service restart activity

**Evidence:** System log event 7031 recorded 13 unexpected Cloudflared service terminations during the current boot pattern, with automatic restart after 20 seconds. The service was running at inspection time and its process used about 34 MB with no material CPU in the spot sample.

**Severity:** Moderate if the tunnel is not needed continuously; Low otherwise  
**Confidence:** Medium

**Why it could cause lag:** A restart loop can cause repeated process creation, network retries, logging, and wakeups. It is more likely to create intermittent background noise or network delay than a broad local CPU bottleneck.

**Recommended next diagnostic step:** Review Cloudflared service logs and correlate restarts with lag timestamps. Confirm the tunnel is intentionally configured to run at boot. Service arguments containing credentials were intentionally omitted from this report.

### 6. C: free-space margin and isolated storage latency

**Evidence:** C: has approximately 38 GB free, or 13.6%. One ESENT warning reported a WebCache write that took 15 seconds to be serviced. However, the KIOXIA NVMe drive is Healthy/Online, targeted storage error events were absent, and repeated disk time/queue measurements were low.

**Severity:** Moderate if free space continues to fall; Low as the immediate cause  
**Confidence:** Low-Medium

**Why it could cause lag:** Windows updates, browser caches, temporary files, and pagefile activity benefit from free space. A single 15-second WebCache write is evidence of an isolated latency event, not proof of failing storage.

**Recommended next diagnostic step:** Keep a larger free-space buffer on C: and correlate future ESENT/WebCache events with actual lag. If long writes recur, collect a longer Resource Monitor or Performance Monitor trace and inspect the storage stack.

### 7. Optional display/audio/peripheral utilities

**Evidence:** EL Display Hub, Epson projector software, Logitech Options+, LanSchool, TeamViewer, CyberLink, Apple Mobile Device, and other third-party components are installed or running. `WavesSysSvc64.exe` crashed once and the Epson virtual-display device is in error.

**Severity:** Low-Moderate  
**Confidence:** Low-Medium

**Why it could cause lag:** Individually these utilities are normally legitimate, but they add services, tray agents, virtual devices, update checks, and hooks into display/audio/input paths.

**Recommended next diagnostic step:** Review which peripherals and remote-management tools are actually in use. Test one vendor utility at a time, preferably after resolving the display-driver error, and retain anything required for school/work hardware.

### 8. Windows Update and Microsoft Defender activity

**Evidence:** Windows Update logged 84 download-start information events and 69 successful Defender security-intelligence installations on 2026-08-22. `MsMpEng.exe`, SearchIndexer, TiWorker, and TrustedInstaller were not high CPU/disk consumers in the collected snapshots.

**Severity:** Low for the observed sustained lag  
**Confidence:** High

**Why it could cause lag:** Update and security scans can create short CPU/disk bursts, especially immediately after boot, but this audit did not observe sustained activity or update failures.

**Recommended next diagnostic step:** If lag clusters around update times, compare a quiet period after servicing completes. Do not disable Defender, Windows Update, or indexing as a performance shortcut.

## D. RESOURCE-HEAVY PROCESSES

Values below are a final read-only snapshot near the end of the audit. CPU is reported from the repeated-rate samples where available; cumulative CPU seconds are intentionally not treated as current CPU usage. I/O is a spot reading and can change rapidly.

| Process / PID | CPU observation | RAM / private | Threads / handles | Disk/I/O spot | Path / publisher / parent | Assessment |
|---|---:|---:|---:|---:|---|---|
| ChatGPT 8092 | Up to ~6% total CPU in sample | 394 / 370 MB | 25 / 1,392 | ~2.2 MB/s in one spot | `C:\Program Files\WindowsApps\OpenAI.Codex_*\app\ChatGPT.exe`; OpenAI; child of ChatGPT 14508; renderer/GPU child command line | Active, legitimate high-use renderer |
| ChatGPT 9300 | Up to ~4% total CPU in sample | 380 / 331 MB | 23 / 335 | ~2.2 MB/s in one spot | Same OpenAI path; renderer child of ChatGPT 14508 | Active, legitimate high-use renderer |
| ChatGPT 14508 | Not a top interval CPU process | 349 / 720 MB | 59 / 1,823 | Low in spot | Same OpenAI path; parent Explorer; main app command line | Highest private memory among inspected processes |
| Chrome 9536 | Not a sustained top interval CPU process | 425 / 501 MB | 30 / 525 | Low in spot | `C:\Program Files\Google\Chrome\Application\chrome.exe`; Google LLC; child of Chrome 19364; renderer command line | Largest individual Chrome working set |
| Chrome 1792 | Up to ~4% total CPU in sample | 401 / 440 MB | 28 / 491 | Low in spot | Same Google path; child of Chrome 19364; renderer command line | Repeated CPU contributor |
| Chrome 976 | GPU engine spot activity; no sustained CPU finding | 260 / 328 MB | 28 / 2,240 | Low in spot | Same Google path; child of Chrome 19364; GPU-process command line | High handle count; normal Chrome multi-process role |
| Dell TechHub Instrumentation SubAgent 8012 | 0 in repeated CPU samples | 313 / 193 MB | 46 / 1,881 | Low in spot | Path not exposed by process CIM; launched by Dell TechHub at `C:\Program Files\Dell\TechHub`; Dell | Large resident background component |
| SupportAssistAgent 18632 | 0 in repeated CPU samples | 273 / 114 MB | 52 / 1,422 | Low in spot | `C:\Program Files\Dell\SupportAssistAgent\bin\SupportAssistAgent.exe`; Dell Inc.; parent services.exe | Large resident support agent |
| MsMpEng 5680 | 0 in repeated CPU samples | 250 / 279 MB | 11 / 728 | Low in spot | `C:\ProgramData\Microsoft\Windows Defender\Platform\4.18.26070.9-0\MsMpEng.exe`; Microsoft; parent services.exe | Resident security process, not a sustained load source here |
| logioptionsplus_agent 14724 | No sustained CPU finding | 116 / 111 MB | 124 / 1,199 | Low in spot | `C:\Program Files\LogiOptionsPlus\logioptionsplus_agent.exe`; Logitech, Inc. | Many threads; optional peripheral utility |
| Memory Compression 3724 | No CPU finding | 496 working set / ~1 MB private | 50 / 0 | Low in spot | Windows-managed memory process | Indicates Windows is compressing memory; not itself proof of harmful paging |

No inspected high-resource process was found running from Temp or Downloads. The AppData processes observed were Opera Browser Assistant (Opera Software, valid signature status returned) and OpenAI Codex runtime components in the expected OpenAI path. No unknown executable was classified as malware.

## E. EVENT LOG FINDINGS

The System and Application logs were reviewed for the preceding seven days and grouped by provider/event type rather than listing every record.

### Meaningful issues

- **Critical display/user-mode driver failure:** DriverFrameworks-UserMode 10111 and 10120 on 2026-08-20 reported a user-mode driver host crash and `MermoDisplayAdapter` going offline.
- **Repeated Intel driver start failure:** Service Control Manager 7000, 76 occurrences, `IntelTACD` file not found.
- **Cloudflared restart activity:** Service Control Manager 7031, 13 occurrences, unexpected termination followed by automatic restart.
- **ACPI/HAL firmware warnings/errors:** HAL events 20 and 21, eight each, reported failure to query/set the hardware real-time clock through an ACPI method. ACPI event 15 occurred twice. These point to firmware/platform noise, not a proven thermal event.
- **Device association errors:** DeviceAssociationService 3502 occurred 26 times and 3503 once. These are consistent with peripheral pairing/endpoint discovery problems and may be unrelated to general performance.
- **Application crashes/hangs:** One `WavesSysSvc64.exe` access-violation crash, one `IDMIntegrator64.exe` access-violation crash, and one Brave 151.1.93.136 Application Hang event were found. They are isolated in this seven-day window, not a repeated dominant crash loop.
- **Storage-latency warning:** One ESENT 508 event reported a 15-second WebCache write. This is the only strong storage-latency signal found, and it was not repeated in the live sampling window.
- **Search:** One Search filter-host termination warning was found. SearchIndexer was low-use during the audit.
- **Power-notification errors:** IPFUF event 17 occurred 16 times. This warrants correlation with the Intel/Dell platform-driver issues if lag changes with battery state.

### Lower-priority or commonly noisy events

Repeated DCOM 10016 warnings, WMI provider registration warnings, DNS timeouts, MariaDB unauthenticated localhost connection warnings, and security-product licensing/certificate messages were present. They do not currently provide a strong explanation for sustained local UI lag.

There were two System Critical events and no Application Critical events in the seven-day window. No unexpected-shutdown event was found in the preceding one-day targeted search. No WHEA hardware errors, Disk/Ntfs/storage-controller errors, or display-driver-reset event 4101 were found in the targeted search.

## F. STARTUP/BACKGROUND SOFTWARE

Windows reported 20 startup entries. GoogleDriveFS appeared in multiple registry hives, which explains duplicate-looking entries; only two GoogleDriveFS processes were observed at the time of the process snapshot.

| Classification | Observed items | Potential benefit / performance trade-off |
|---|---|---|
| Windows/security | SecurityHealth; Microsoft Edge auto-launch | Security tray visibility is useful. Edge auto-launch can add background memory if Edge is not used. |
| Driver/hardware | Epson printer helper; RtkAudUService; WavesSvc; Logitech Download Assistant entries; EL Display Hub; RAKK KALA mouse shortcut | Needed for the associated printer, audio, mouse, or display hardware. If unused, each can add startup delay or a resident helper; removing/disabling one may reduce convenience or device features. |
| Sync/useful applications | GoogleDriveFS; Discord; Internet Download Manager; Perplexity | Useful when actively used. Sync and update helpers can create disk/network activity and memory use. |
| Optional application helpers | Opera Browser Assistant; Insta360 Studio QuickLook; uTorrent minimized; Microsoft Edge auto-launch | Can improve quick launch/integration, but are reasonable candidates for user review if unused. |
| User startup shortcuts | Ollama; OpenClaw Gateway; RAKK KALA Wireless Gaming Mouse | These may be intentional. If not needed each login, not launching them can reduce boot-time work; the downside is losing the expected local service or device integration. |

The audit did not disable or change any startup item. Startup entries alone are not proof that software is unnecessary.

## G. STORAGE ASSESSMENT

- Physical storage is a KIOXIA EG6 512 GB NVMe SSD. Windows Storage Management reports HealthStatus Healthy, OperationalStatus Online, and the disk is the boot/system disk.
- C: has approximately 13.6% free space. This is not an immediate full-disk condition, but it is a smaller operating margin than desirable for caches, updates, and temporary files.
- Repeated live measurements showed 0–8% physical disk time, zero average queue, and low throughput. This rules out a sustained 100%-disk condition during the audit window.
- One ESENT WebCache event reported a 15-second write. Treat it as an intermittent-latency clue only; it does not prove SSD failure.
- `Get-StorageReliabilityCounter` could not access a CIM resource, so SMART-like temperature, wear, and latency counters were unavailable through the built-in query used.
- No targeted seven-day Disk, NTFS, stornvme, storahci, or WHEA error pattern was found.

**Assessment:** Storage is not the leading observed cause. Preserve more free space on C: and collect a longer trace if the 15-second write pattern repeats.

## H. MEMORY ASSESSMENT

- Physical RAM: 15.69 GB visible.
- Available RAM ranged from 4.36 GB at the initial baseline to 5.19 GB during repeated sampling; the repeated window was approximately 32–34% free.
- Commit use was approximately 62–64%; the observed commit limit was about 18.44 GB.
- The active pagefile was `C:\pagefile.sys`, allocated at 2,816 MB. Current usage was 28 MB near the end of the audit; peak usage since boot was 127 MB.
- Pages/sec was 38 only in the first sample and zero thereafter; page reads similarly fell to zero. This does not support sustained paging as the current root cause.
- Chrome and ChatGPT/Codex together used about 3.85 GB working set. Dell/SupportAssist/ServiceShell-related processes used about 1.86 GB working set. This is enough to reduce headroom for additional applications even when the pagefile is not under pressure.
- Memory Compression used about 496 MB of working set. That is a Windows memory-management response and should be interpreted with the low pagefile activity, not as a standalone fault.

**Assessment:** Moderate memory pressure from workload and resident utilities is plausible, but insufficient RAM or active paging was not demonstrated as the sole cause.

## I. DRIVER/HARDWARE ASSESSMENT

- Intel Graphics is healthy in WMI with driver version 32.0.101.7085.
- Epson Projection Idd Device is the only problem PnP device returned: `ROOT\DISPLAY\0000`, ConfigManager error code 22, status Error.
- Easy&Light Display HUB virtual HID and virtual display devices report OK, but their presence adds another display layer.
- The critical MermoDisplayAdapter user-mode driver crash is the strongest hardware/driver evidence connected to intermittent UI lag.
- IntelTACD is a stopped kernel-driver service with Win32 exit code 2, and its repeated missing-file start failures should be repaired through the OEM driver package.
- Intel Dynamic Tuning/Innovation Platform Framework services were running. The audit did not prove thermal throttling, power-limit throttling, or a bad CPU frequency state.
- BIOS is Dell 1.16.5 dated 2026-06-16. No automatic BIOS or driver update was performed.

## J. WINDOWS SERVICES / MAINTENANCE ASSESSMENT

Windows reported 303 total services and 137 running. Thirteen running services were clearly third-party by executable path, including Dell TechHub, Dell SupportAssist, Dell Client Management, EL Display Hub, Epson projector, Intel Graphics Software, LanSchool, Logi Options+, CyberLink, Apple Mobile Device, TeamViewer, and Cloudflared.

Notable scheduled tasks included three CyberLink CLToast tasks, OneDrive updater/startup/reporting tasks, two Opera updater tasks, Chrome Platform Experience Helper tasks, Zoom updater, PowerDirectorStyleAgent, and an Intelligo audio session task. Fifteen non-Microsoft tasks were in Ready or Running state in the filtered inspection.

Windows Update was active around the audit time, but the events were successful Defender security-intelligence downloads/installs. TiWorker and TrustedInstaller were not active high consumers, SearchIndexer was about 35 MB with no meaningful CPU use, and no update-failure pattern was found.

Microsoft Defender was the only antivirus product reported by Security Center. Its service was running, signatures were current, and `MsMpEng.exe` was not a sustained CPU/disk consumer. However, `Get-MpComputerStatus` reported `RealTimeProtectionEnabled`, `OnAccessProtectionEnabled`, `BehaviorMonitorEnabled`, `IoavProtectionEnabled`, and `NISEnabled` as false. That is a security-state finding, not a performance finding; it should be verified in Windows Security before making any change. Defender should not be disabled as a performance remedy.

## K. MOST LIKELY ROOT CAUSE

Based strictly on the collected evidence, the most likely pattern is:

1. An intermittent display/virtual-display driver problem is causing UI-specific lag or redraw stalls. The critical user-mode driver crash and the current Epson projection device error are stronger evidence than the resource counters.
2. The active workload is unusually heavy for a 16 GB laptop: many Chrome and Codex/ChatGPT processes plus a large Dell support/telemetry footprint. This likely contributes to broad sluggishness and makes any driver stall more noticeable.
3. The IntelTACD missing-driver loop and Cloudflared restart activity are secondary background reliability issues that may create bursts and should be repaired or explained.

There is no evidence in this audit for sustained 100% disk usage, a continuously full CPU, persistent pagefile thrashing, a failing NVMe device, or a sustained Defender/Search/Windows Update scan. The conclusion is therefore not “replace the SSD” or “disable Windows services”; the display-driver path and background footprint deserve first investigation.

## L. RECOMMENDED ACTIONS

### SAFE / LOW RISK

- Record the exact time and symptom when lag occurs, then correlate it with System events 10111/10120, 7000, and 7031.
- Confirm whether the lag occurs with the Epson projector and/or Easy&Light virtual display connected, and whether it is display-specific or affects all applications.
- During a reproduction, compare the aggregate Chrome and ChatGPT/Codex working set with a lighter session. Review Chrome's built-in task manager for individual tabs/extensions.
- Review user-owned startup entries one at a time. Candidates for review are Opera Assistant, uTorrent, Perplexity, Discord, IDMan, Insta360 QuickLook, Ollama, OpenClaw Gateway, EL Display Hub, Google Drive, and Edge auto-launch. The expected benefit is lower login-time work and resident memory; the downside is losing automatic sync, update checks, device integration, or local services.
- Keep a larger free-space buffer on C: and monitor whether WebCache/ESENT long-write warnings recur. Do not delete files automatically as part of this audit.
- Verify the Defender protection-state discrepancy in Windows Security. Do not turn security features off to chase performance.
- Manually compare the same workload on battery and AC power. This audit observed battery power only and did not change the Balanced plan.

### MODERATE RISK

- Repair or update the Dell/Intel display, Epson projection, and Easy&Light Display Hub packages using official vendor support and the exact model/device identifiers. Driver changes can affect display output, so create a recovery point or confirm a recovery path first.
- Repair the Intel platform-driver package associated with `IntelTACD` rather than deleting the stopped driver registration.
- Review and repair Cloudflared service configuration/logs if the tunnel is required; if it is not required continuously, decide on its startup behavior deliberately.
- Review Dell SupportAssist/TechHub feature requirements and test optional components in a controlled manner. Preserve any component required for warranty diagnostics, hardware controls, or organizational management.
- If the ESENT long-write symptom repeats, collect a longer Resource Monitor/Performance Monitor trace and then use vendor storage diagnostics. Do not infer SSD failure from one event.

### DO NOT DO WITHOUT FURTHER INVESTIGATION

- Do not delete or manually unregister `IntelTACD`, display drivers, virtual-display devices, or registry entries.
- Do not disable Defender, Windows Update, Windows Search, Dynamic Tuning, or other core services as a generic optimization.
- Do not change pagefile sizing, power plans, BIOS settings, or thermal policies based on this snapshot alone.
- Do not terminate processes or stop services based only on their working set, cumulative CPU time, or handle count.
- Do not classify Chrome, ChatGPT/Codex, Dell, Epson, Logitech, or Cloudflared as malware solely because they are resource consumers or use AppData/Program Files paths.

## Audit conclusion

The strongest next step is a controlled display-driver/peripheral correlation and repair, followed by reducing or validating optional background load. The machine was not under sustained CPU, memory-paging, or disk saturation during the audit window, so a single global resource metric would not explain intermittent lag.

**No system changes were made during this audit.**
