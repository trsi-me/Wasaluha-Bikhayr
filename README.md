# وصلها بخير

تطبيق ويب يربط مستفيداً (`needy`) يضيف حالة بمحتاج، ومتبرعاً (`donor`) يوفّر الحالة. الشعار في التوثيق السابق: «خيرك يوصل أسرع». مكان الاستلام الثابت في الكود: جمعية يد الخير.

## 1 ما هو المشروع

واجهة HTML تحت `public/` تتحدث JSON مع `api/`. الصفحة `index.php` تعيد التوجيه إلى `public/index.html`. الحالات أنواع مثل طعام وملابس وأدوية ونقدي، بحالات `open` و`claimed` و`fulfilled` و`closed`.

## 2 لماذا وُجد

التوثيق السابق: إيصال طلبات المحتاجين للمتبرعين بسرعة. الكود يتيح إضافة حالة، وتصفحاً، وضغط «يمكن التوفير» الذي يعلّم الحالة `claimed` وينشئ إشعاراً.

## 3 المستخدمون

| الدور | القدرة في الكود |
| --- | --- |
| زائر | الرئيسية وإحصاءات وحالات حديثة |
| needy | إضافة حالة، عرض حالاته، حذف المفتوحة |
| donor | توفير حالة `open`، عداد تبرعات، إشعارات |

بذرة `sql/init_db.sql` تدرج خمسة مستخدمين بهاش bcrypt واحد: أحمد محمد `ahmed@example.com` متبرع، فاطمة علي `fatima@example.com` مستفيدة، خالد سعيد `khalid@example.com` متبرع، سارة أحمد `sara@example.com` مستفيدة، محمد حسن `mohammed@example.com` متبرع. أرقام جوال موجودة في الملف ولا تُنسخ هنا.

التوثيق السابق لا يعطي كلمة جاهزة ويطلب استبدال هاش. فحص محلي بـ `password_verify`: الهاش المشترك يطابق الكلمة `password` ولا يطابق `password123`.

## 4 القدرات

- تسجيل: اسم 3 أحرف على الأقل، بريد أو جوال، كلمة 6 أحرف على الأقل وتأكيد، دور `donor` أو `needy`.
- دخول بالبريد أو الجوال.
- فحص جلسة `api/auth.php?action=check_session`.
- خروج يمسح الكوكي ويدمر الجلسة.
- حالات: جلب، أحدث، إحصاءات، إضافة للمستفيد، حذف، توفير.
- حساب: بيانات، إشعارات، تعليم مقروء، طريقة تسليم.
- سجل عمليات في `logs/actions_YYYY-MM-DD.json` وأخطاء في `logs/errors_*.json`.
- ملفات سجل موجودة بتاريخ 2025-11-10.

## 5 كيف يعمل

`config/db.php` يفتح PDO باسم `wasaluha_bikhayr` ويخفي أخطاء PHP ويسجلها. `api/auth.php` و`api/cases.php` و`api/account.php` تقرأ `action`. الواجهة `assets/js/api.js` تغلف الطلبات وتقرأ `csrf_token` من فحص الجلسة.

`cases.php` عند التوفير يفتح معاملة: يقرأ حالة `open`، يحدّثها إلى `claimed`، يدرج إشعاراً فيه `case_id`.

## 6 أمثلة من الكود والبيانات

التسجيل:

```php
password_hash($password, PASSWORD_DEFAULT)
```

الدخول: `password_verify` ثم `session_regenerate_id(true)`.

تناقض اسم القاعدة:

| المصدر | الاسم |
| --- | --- |
| `config/db.php` والتوثيق السابق | `wasaluha_bikhayr` |
| `sql/init_db.sql` | `u741730784_wasaluha_bikha` |

`sql/add_notification_fields.sql` يضيف `notifications.case_id` و`notifications.delivery_method` ومفتاحاً أجنبياً. `init_db.sql` ينشئ `notifications` بلا هذين العمودين. `cases.php` يدرج `case_id`. تشغيل التوفير على مخطط init وحده يفشل حتى يُنفَّذ ملف الأعمدة.

فحص CSRF في التوفير والحذف:

```php
$input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')
```

لا سطر في PHP يكتب `$_SESSION['csrf_token']`. فحص الجلسة يعيد المفتاح إن وُجد وإلا سلسلة فارغة. استنتاج من الكود: طلب يرسل `csrf_token` فارغاً يطابق الجلسة الفارغة فيمر الشرط.

التوثيق السابق يقول إن التطبيق يستخدم حماية CSRF. الفحص موجود. التوليد غير موجود في الملفات الحالية.

## 7 رحلة المستخدم

1. `public/index.html` تعرض إحصاءات من `get_stats` وحالات من `get_recent_cases`.
2. `register.html` ثم `login.html`.
3. المستفيد: `add_case.html` ثم يرى حالاته في `cases.html` أو `account.html`.
4. المتبرع: `cases.html` ثم توفير. الرد يتضمن `delivery_place` = جمعية يد الخير.
5. المستفيد يرى إشعاراً. `account.html` يتيح اختيار طريقة تسليم عبر `setDeliveryMethod`.
6. `logout.html` يستدعي خروج API.

## 8 الوحدات

| المسار | الدور |
| --- | --- |
| `public/*.html` | واجهات |
| `assets/js/api.js`, `main.js`, `assets/css/style.css` | عميل |
| `api/auth.php`, `cases.php`, `account.php` | JSON |
| `donate_action.php` | توفير بديل عن `action=donate` |
| `config/db.php` | PDO |
| `includes/logger.php` | ملفات JSON |
| `sql/init_db.sql`, `add_notification_fields.sql` | مخطط |
| `index.php` | توجيه |
| `.htaccess`, `logs/.htaccess` | Apache |
| `logs/` | سجلات يوم 2025-11-10 |

التوثيق السابق يذكر `STRUCTURE.md`. الملف غير موجود في الشجرة الحالية.

## 9 الكيانات

### users

`id`, `name`, `email`, `phone`, `password`, `role` (`donor` أو `needy` والافتراضي `needy`), `created_at`.

### cases

`id`, `user_id`, `item_name`, `quantity` افتراضي 1, `type`, `status`, `urgent`, `amount`, `created_at`. مفتاح أجنبي مع `ON DELETE CASCADE`.

### notifications

في init: `id`, `from_user`, `to_user`, `message`, `created_at`, `read_flag`.

بعد الترقية: `case_id`, `delivery_method`.

بذرة الحالات تشمل أرزاً وزيتاً وملابس أطفال وأدوية وسكراً وبطاطين ودقيقاً وحليب أطفال ومبالغ نقدية 500 و1000 و750 و300. بعض الصفوف `claimed` أو `fulfilled`.

## 10 الصلاحيات

| الإجراء | الدور |
| --- | --- |
| إضافة وحذف حالة | `needy` وصاحب الصف عند الحذف |
| توفير | `donor` وحالة `open` |
| الإحصاءات والجلب العام | حسب الدالة؛ جزء منها بلا دخول |
| الحساب | جلسة |

لا دور مدير.

## 11 الأتمتة

غير موجود: cron أو بريد. الإشعار صف في الجدول عند التوفير. النص في `cases.php`: «تم توفير حالتك بنجاح». سجل `logAction` يكتب أيضاً جملة الاستلام من جمعية يد الخير.

## 12 التكامل بين الوحدات

`api.js` يوحد الاستدعاءات. `main.js` وسكربتات الصفحات تستدعي التوفير والحذف والتعليم كمقروء. `donate_action.php` و`api/cases.php?action=donate` كلاهما مسار توفير. الواجهة في `api.js` تستدعي مسار `donate` عبر الغلاف (دالة `donate`).

طريقة التسليم تُحدَّث من `account.php` بعد وجود العمود `delivery_method`.

## 13 المصطلحات

| المصطلح | هنا |
| --- | --- |
| حالة | صف `cases` |
| توفير | انتقال `open` إلى `claimed` |
| urgent | علم 0 أو 1 |
| جمعية يد الخير | نص ثابت لمكان الاستلام |

## 14 الأسئلة الشائعة

**قاعدة غير موجودة رغم استيراد SQL.**  
الاستيراد ينشئ `u741730784_wasaluha_bikha` والاتصال يطلب `wasaluha_bikhayr`.

**التوفير يفشل بخطأ عمود.**  
`case_id` يأتي من `add_notification_fields.sql` وليس من `init_db.sql`.

**CSRF يرفض الطلب.**  
إن أرسل العميل رمزاً غير فارغ والجلسة بلا رمز، الشرط يفشل. الرمز لا يُولَّد في PHP.

**الخطوط.**  
التوثيق السابق يذكر `assets/fonts/IBM-Plex-Sans-Arabic.*`. الشجرة الحالية تظهر `assets/fonts/OFL.txt`.

## 15 مخطط المعمارية

```
public/*.html
    |
    v
assets/js/api.js
    |
    v
api/auth.php | cases.php | account.php
    |
    v
config/db.php PDO
    |
    v
MySQL + logs/*.json
```

## 16 التقنيات المستخدمة

HTML، CSS، JavaScript، PHP، MySQL عبر PDO. التوثيق السابق يطلب PHP 8.1 أو أحدث وMySQL 8.0 أو أحدث وApache، ويقول إن البناء بلا مكتبات خارجية.

`public/index.html` يحمّل Font Awesome 6.4.0 من `cdnjs.cloudflare.com`. هذا يخالف جملة «بدون أي مكتبات خارجية» في التوثيق السابق. الأيقونات في الصفحة أصناف `fas`.

`.htaccess` يعيد كتابة المسارات إلى `public/` ويستثني `api` و`assets` و`config` و`includes` و`sql` و`logs`.

## 17 شجرة الملفات

```
Wasaluha Bikhayr/
├── index.php
├── donate_action.php
├── .htaccess
├── public/
├── api/
├── config/db.php
├── includes/logger.php
├── assets/css assets/js assets/fonts/OFL.txt
├── sql/init_db.sql
├── sql/add_notification_fields.sql
├── logs/
└── README.md
```

## 18 الواجهة

نمط داكن بأخضر وأصفر كما يقول التوثيق السابق. صفحات: الرئيسية، الدخول، التسجيل، الحالات، إضافة حالة، الحساب، الخروج.

أيقونة تبويب غير موجودة في الملفات الحالية.

تنسيق التاريخ في `account.html` دالة `formatDate` محلية في الصفحة، منفصلة عن قاعدة تنسيق المشروع العامة.

## 19 الخادم

JSON بحقل `status` قيم `ok` أو `error` وحقل `msg`. `display_errors` مطفأ في API والإعداد. فشل القاعدة رسالة عامة للمستخدم، والتفصيل في `logError`.

`index.php` يرسل `Location: public/index.html`.

## 20 مسار الطلب

دخول: POST JSON إلى `api/auth.php?action=login` بحقل `login` و`password`.

تسجيل: `action=register`.

حالات: `api/cases.php?action=` إحدى `get_cases`, `add_case`, `delete_case`, `get_stats`, `get_recent_cases`, `donate`.

حساب: `api/account.php` لمعلومات المستخدم والإشعارات وتعليم القراءة وطريقة التسليم.

توفير بديل: `donate_action.php` بنفس فكرة الدور وCSRF والمعاملة.

## 21 قاعدة البيانات

مخطط init ينشئ القاعدة ذات الاسم الطويل وثلاثة جداول وبذرة حالات وإشعارات. الترقية تضيف عمودين للإشعار. الاتصال في PHP اسم آخر. لا تُنسخ هاشات البذرة هنا.

## 22 نقاط الدخول

| المسار | action أو الدور |
| --- | --- |
| `api/auth.php` | login, register, logout, check_session |
| `api/cases.php` | الحالات الست |
| `api/account.php` | معلومات وإشعارات |
| `donate_action.php` | توفير للمتبرع |
| `public/*.html` | صفحات |
| `index.php` | توجيه |

## 23 المصادقة

جلسة `user_id`, `user_name`, `user_role`. تجديد المعرف بعد دخول ناجح. الخروج يمسح كوكي الجلسة. الكلمة مجزأة بـ `PASSWORD_DEFAULT`. الدخول بالبريد أو الجوال.

لا `password_verify` لمسار آخر. لا حد لمعدل المحاولات. فشل الدخول يُسجل `LOGIN_FAILED` مع قيمة `login` بلا كلمة السر.

## 24 الأمان الموجود فعلياً

- PDO بمعاملات و`ATTR_EMULATE_PREPARES => false`.
- `password_hash` / `password_verify`.
- `session_regenerate_id` بعد الدخول.
- فحص دور على الإضافة والحذف والتوفير.
- معاملة عند التوفير.
- `htmlspecialchars` في دوال `escapeHtml` داخل الصفحات عند تركيب النص.
- إخفاء أخطاء PHP.
- `logs/.htaccess` فيه `Deny from all`.
- مقارنة CSRF موجودة.

حدود:

- رمز CSRF لا يُنشأ، والمقارنة مع سلسلة فارغة تنجح.
- `.htaccess` الجذر لا يمنع جلب `/config/` و`/sql/` مباشرة؛ شروط إعادة الكتابة تستثنيهما حتى لا تُنقل إلى `public/`، وليست قاعدة منع.
- أسرار الاتصال في التوثيق السابق مثال root فارغ، والملف كذلك. اسم القاعدة لا يطابق SQL.
- لا أيقونة موقع.
- سجلات JSON قد تحوي بريداً وجوالاً من بيانات الدخول الناجح (`logAction` يمرر email وphone).

## 25 الإعدادات

`config/db.php`:

| المتغير | القيمة |
| --- | --- |
| `$db_host` | `localhost` |
| `$db_name` | `wasaluha_bikhayr` |
| `$db_user` | `root` |
| `$db_pass` | فارغة |

لا `.env`.

## 26 التكاملات الخارجية

Font Awesome من CDN. غير موجود: بوابة دفع أو بريد. التبرع تغيير حالة داخل القاعدة.

## 27 المهام المجدولة

غير موجود في الملفات الحالية.

## 28 الملفات والمرفقات

لا رفع صور للحالات. الخطوط: `OFL.txt` موجود وملفات الخط المذكورة في التوثيق السابق غير ظاهرة بالاسم `IBM-Plex-Sans-Arabic.*` في الشجرة الحالية.

## 29 السجلات

`includes/logger.php` يلحق سطراً JSON في ملف يومي: وقت، طابع يونكس، IP، مستخدم، إجراء، بيانات، وكيل، مسار، طريقة. الأخطاء في ملف `errors_`. مجلد `logs` فيه `actions_2025-11-10.json` و`errors_2025-11-10.json`. الصيغة في الملف `Y-m-d` وليست صيغة العرض العربية للمشروع.

`.htaccess` الجذر يضبط `log_errors` و`error_log` إلى `error.log`.

## 30 التثبيت

1. PHP وMySQL وApache إن رغبت بقواعد `.htaccess`. التوثيق السابق يذكر XAMPP أو WAMP.
2. أنشئ قاعدة بالاسم الذي يقرأه `config/db.php` وهو `wasaluha_bikhayr`، ثم نفّذ جداول `init_db.sql` داخلها، أو غيّر `$db_name` إلى `u741730784_wasaluha_bikha` بعد الاستيراد كما هو.
3. نفّذ `sql/add_notification_fields.sql` قبل تجربة التوفير.
4. افتح `public/index.html` أو المسار الذي يعيد `index.php` توجيهه. التوثيق السابق يذكر `http://localhost/Wasaluha Bikhayr/public/` والمسافة في اسم المجلد جزء من المسار.
5. ادخل ببريد من البذرة والكلمة `password`، أو سجّل حساباً جديداً.

## 31 دليل التطوير

أضف `action` في ملف API المناسب وفرعاً في `api.js`. أعمدة الإشعار الجديدة لازم تبقى متزامنة مع INSERT التوفير. ولّد `csrf_token` في الجلسة عند الدخول إن أردت أن يرفض الفحص الطلبات الفارغة.

## 32 النشر

غير موثق كمنصة. Apache يقرأ `.htaccess` (`RewriteBase /` يفترض أن الموقع على جذر المضيف، بينما التشغيل المحلي غالباً داخل مجلد فرعي). أخفِ مجلدات `config` و`sql` و`logs` عن الويب. عطّل الاعتماد على CDN إن كان الاتصال غير مضمون، أو أبقه كما هو الآن.

## 33 النسخ الاحتياطي

غير موثق. انسخ القاعدة وملفات `logs/`. السجلات فيها بيانات اتصال ناجحة (بريد وجوال) بلا كلمة سر.

## 34 استكشاف الأخطاء

| العرض | المصدر |
| --- | --- |
| خطأ اتصال عام | الاسم `wasaluha_bikhayr` غير منشأ أو MySQL متوقف. التفصيل في `logs/errors_*.json` |
| عمود case_id | الترقية لم تُنفَّذ |
| كلمة التوثيق السابق غير موجودة | الهاش المزروع يطابق `password` |
| الخط | ملفات الخط غير ظاهرة سوى OFL |
| إعادة الكتابة | `RewriteBase /` لا يطابق مجلداً فرعياً |

## 35 الاعتماديات

PHP مع PDO MySQL. متصفح. Apache لملف `.htaccess` (بدونه يعمل PHP إن فُتحت الملفات مباشرة). Font Awesome عبر الإنترنت للصفحة الرئيسية.

## 36 القيود

- اسما قاعدة مختلفان.
- CSRF بلا توليد.
- مساران للتوفير.
- لا مدير.
- لا دفع فعلي.
- مكان استلام ثابت.
- مكتبات CDN تخالف جملة التوثيق السابق.
- `STRUCTURE.md` مذكور وغير موجود.

## 37 الحالة الحالية

واجهة وحالات وبذرة وإشعارات ومسجل ملفات. التشغيل يحتاج توحيد اسم القاعدة وتنفيذ ترقية أعمدة الإشعار. الدخول للبذرة بالكلمة `password`.

## 38 قرارات معمارية

- HTML ثابت وAPI منفصلة.
- دوران `donor` و`needy`.
- توفير داخل معاملة مع إشعار.
- تجزئة `PASSWORD_DEFAULT`.
- سجل JSON يومي.
- مكان استلام نصي ثابت.

## 39 سجل التغييرات

غير موجود كملف إصدارات. `add_notification_fields.sql` ترقية مخطط الإشعار فوق `init_db.sql`. سجلات التشغيل الموجودة مؤرخة 2025-11-10.

## System Overview

منصة تبرع بسيطة: المستفيد ينشر حاجة، والمتبرع يعلّمها متوفرة، ويصل إشعار، والاستلام من جمعية يد الخير. البيانات في MySQL والواجهة في `public/` والعمليات في `api/`.

## Quick Reference

| البند | القيمة |
| --- | --- |
| قاعدة PHP | `wasaluha_bikhayr` |
| قاعدة ملف SQL | `u741730784_wasaluha_bikha` |
| كلمة هاش البذرة | `password` |
| مثال بريد متبرع | `ahmed@example.com` |
| مثال بريد مستفيدة | `fatima@example.com` |
| الواجهة | `public/index.html` |
| ترقية الإشعار | `sql/add_notification_fields.sql` |

## Quick Start

1. أنشئ `wasaluha_bikhayr` واستورد الجداول من `init_db.sql` (تخطَّ سطر `CREATE DATABASE` أو عدّل الاسم).
2. نفّذ `add_notification_fields.sql`.
3. شغّل Apache/PHP وافتح `public/index.html`.
4. ادخل ببريد مزروع وكلمة `password`، أو سجّل من `register.html`.

## For Non-Technical Users

من الرئيسية ترى أرقاماً وطلبات حديثة. المستفيد يسجّل ويضيف ما يحتاجه. المتبرع يسجّل ويضغط توفير على الطلب المفتوح. تظهر رسالة أن الاستلام من جمعية يد الخير. الحساب يعرض إشعاراتك.

## For Developers

وحّد اسم القاعدة قبل أي تجربة. `password_verify` على هاش البذرة يطابق `password`. عمود `case_id` شرط لمسار التوفير. رمز CSRF يُقارَن ولا يُملأ في `auth.php`. `donate_action.php` يكرر `action=donate`. Font Awesome من CDN رغم نص التوثيق السابق. لا تنسخ هاش SQL إلى وثائق جديدة.
