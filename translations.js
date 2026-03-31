/**
 * translations.js — UI text strings for LibreSpeed
 *
 * Current language: Burmese (မြန်မာဘာသာ)
 *
 * To switch language, replace all values below with your target language.
 * English originals are shown in comments on each line.
 * Do NOT edit speedtest.js or speedtest_worker.js.
 */

var UI_STRINGS = {
  // Page metadata
  pageTitle: "LibreSpeed — အမြန်နှုန်းစမ်းသပ်မှု", // "LibreSpeed — Speed Test"
  pageHeading: "အမြန်နှုန်းစမ်းသပ်မှု",             // "Speed Test"

  // Navbar
  navBrand: "Portal",
  navSpeedtest: "အမြန်နှုန်းစစ်ဆေးခြင်း", // "Speed Test"

  // Loading / server selection
  loading: "ဆာဗာရွေးချယ်နေသည်…",   // "Selecting a server…"
  noServers: "ဆာဗာများ မရရှိနိုင်ပါ", // "No servers available"

  // Start/stop button
  startBtn: "စတင်ပါ",   // "Start"
  abortBtn: "ရပ်တန့်ပါ", // "Abort"

  // Controls
  privacyLink: "ကိုယ်ရေးအချက်အလက်", // "Privacy"
  closeBtn: "ပိတ်မည်",               // "Close"
  serverLabel: "ဆာဗာ :",              // "Server:"

  // Test metric labels
  pingLabel: "ပင်",        // "Ping"
  jitterLabel: "ဂျစ်တာ",  // "Jitter"
  downloadLabel: "ဒေါင်းလုတ်", // "Download"
  uploadLabel: "အပ်လုတ်",      // "Upload"

  // Units (keep as-is — universal technical terms)
  pingUnit: "ms",
  jitterUnit: "ms",
  downloadUnit: "Mbit/s",
  uploadUnit: "Mbit/s",

  // Results sharing
  shareHeading: "ရလဒ်မျှဝေပါ",          // "Share results"
  testIdLabel: "စမ်းသပ်မှတ်တမ်း ID :",   // "Test ID:"
  linkCopied: "လင့်ကူးယူပြီးပါပြီ",      // "Link copied"
  sourceCode: "အရင်းအမြစ်ကုဒ်",          // "Source code"

  // Results image alt text — {dl}, {ul}, {ping}, {jitter} are replaced automatically
  resultsAlt: "ဒေါင်းလုတ်: {dl} Mbps, အပ်လုတ်: {ul} Mbps, ပင်: {ping} ms, ဂျစ်တာ: {jitter} ms",

  // Privacy policy
  privacyTitle: "ကိုယ်ရေးကိုယ်တာ မူဝါဒ", // "Privacy Policy"
  privacyBody: `
    <p>ဤ HTML5 အမြန်နှုန်းစမ်းသပ်မှုဆာဗာသည် တယ်လီမက်ထရီ ဖွင့်ထားသောအခြေအနေဖြင့် ကွန်ဖီဂျာပြုလုပ်ထားသည်။</p>

    <h4>ကျွန်ုပ်တို့ ဘာဒေတာကောက်ယူသနည်း</h4>
    <p>စမ်းသပ်မှုပြီးဆုံးသောအခါ အောက်ပါဒေတာများကို ကောက်ယူသိမ်းဆည်းသည်:
      <ul>
        <li>စမ်းသပ်မှတ်တမ်း ID</li>
        <li>စမ်းသပ်မှုပြုလုပ်သည့်အချိန်</li>
        <li>စမ်းသပ်မှုရလဒ်များ (ဒေါင်းလုတ်နှင့် အပ်လုတ်အမြန်နှုန်း၊ ပင်နှင့် ဂျစ်တာ)</li>
        <li>IP လိပ်စာ</li>
        <li>ISP သတင်းအချက်အလက်</li>
        <li>ခန့်မှန်းတည်နေရာ (IP လိပ်စာမှ ရရှိသော၊ GPS မဟုတ်ပါ)</li>
        <li>User agent နှင့် browser locale</li>
        <li>စမ်းသပ်မှုမှတ်တမ်း (ကိုယ်ရေးကိုယ်တာ အချက်အလက် မပါဝင်ပါ)</li>
      </ul>
    </p>

    <h4>ကျွန်ုပ်တို့ ဒေတာကို မည်သို့အသုံးပြုသနည်း</h4>
    <p>ဤဝန်ဆောင်မှုမှတဆင့် ကောက်ယူသောဒေတာကို အောက်ပါရည်ရွယ်ချက်များအတွက် အသုံးပြုသည်:
      <ul>
        <li>စမ်းသပ်မှုရလဒ်များ မျှဝေနိုင်ရန် (ဖိုရမ်စသည်တို့အတွက် မျှဝေနိုင်သောပုံ)</li>
        <li>ကျွန်ုပ်တို့ဘက်မှ ပြဿနာများကို ရှာဖွေဖော်ထုတ်ရန် ဝန်ဆောင်မှုတိုးတက်ကောင်းမွန်ရန်</li>
      </ul>
      ကိုယ်ရေးကိုယ်တာ အချက်အလက်ကို တတိယပုဂ္ဂိုလ်များသို့ မဖော်ထုတ်ပါ။
    </p>

    <h4>သင်၏ သဘောတူညီချက်</h4>
    <p>စမ်းသပ်မှုစတင်ခြင်းဖြင့် ဤကိုယ်ရေးကိုယ်တာ မူဝါဒ၏ သတ်မှတ်ချက်များကို သဘောတူသည်ဟု မှတ်ယူသည်။</p>

    <h4>ဒေတာဖျက်သိမ်းရေး</h4>
    <p>
      သင်၏ အချက်အလက်များကို ဖျက်သိမ်းလိုပါက စမ်းသပ်မှု ID သို့မဟုတ် သင်၏ IP လိပ်စာ တစ်ခုခုကို ပေးပို့ရမည်ဖြစ်သည်။
      ဤသည်မှာ သင်၏ဒေတာကို ခွဲခြားသိရှိနိုင်သော တစ်ခုတည်းသောနည်းလမ်းဖြစ်ပြီး ဤအချက်အလက်မပါဘဲ
      သင်၏တောင်းဆိုချက်ကို ဖြည့်ဆည်းပေးနိုင်မည် မဟုတ်ပါ။<br/><br/>
      ဖျက်သိမ်းမှုတောင်းဆိုချက်များအတွက် ဤအီးမေးလ်လိပ်စာသို့ ဆက်သွယ်ပါ:
      <a href="mailto:PUT@YOUR_EMAIL.HERE">ဒီဗလပ်မာမှ ဖြည့်သွင်းရမည်</a>
    </p>
  `,
};
