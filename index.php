<!DOCTYPE html>
<html lang="ru">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Первая помощь: практический курс для каждого</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="api/visit.php" defer></script>
    <script src="js/main.js" defer></script>
    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=113678602', 'ym');

        ym(113678602, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/113678602" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
</head>
<body>
    <header class="hero">
        <div class="container hero-content">
            <span class="hero-badge">Офлайн-курс · 21 ноября 2026</span>
            <h1>Первая помощь: практический курс для каждого</h1>
            <p class="hero-text">
                Научитесь действовать уверенно в экстренной ситуации: остановить кровотечение,
                провести сердечно-легочную реанимацию, помочь при травмах и ожогах до приезда
                медиков. Курс построен на практике с инструкторами, которые каждый день работают
                с реальными вызовами.
            </p>
            <div class="hero-actions">
                <button type="button" class="btn btn-register hero-register">Оставить заявку на базовый курс</button>
                <a class="hero-secondary" href="#pricing">Сравнить тарифы</a>
            </div>
            <p class="hero-price">4 900 ₽ · очный курс 10:00–18:00 · заявка без оплаты</p>
            <div class="meta-grid">
                <div class="meta-card">
                    <strong>Дата</strong>
                    21 ноября 2026, суббота
                </div>
                <div class="meta-card">
                    <strong>Время</strong>
                    10:00 – 18:00, перерыв на обед
                </div>
                <div class="meta-card">
                    <strong>Место</strong>
                    Москва, ул. Примерная, 10, учебный центр «Спаси-Себя»
                </div>
            </div>
            <div class="hero-note">
                На курсе вы отработаете навыки на манекенах и отработаете сценарии, которые
                помогают сохранить спокойствие и принимать правильные решения под давлением времени.
                После занятия вы получите сертификат и памятку по алгоритмам первой помощи.
            </div>
        </div>
    </header>

    <main>
        <section class="section registration-section" id="registration">
            <div class="container">
                <div class="registration-panel">
                    <h2 class="section-title">Записаться на курс</h2>
                    <p class="section-lead registration-summary" id="registration-summary" aria-live="polite">
                        <strong id="registration-tariff">Базовый — 4 900 ₽</strong>
                        <span id="registration-inclusions">Полный однодневный курс, сертификат, памятка и кофе-брейки.</span>
                        <span>21 ноября · 10:00–18:00. Заявка без оплаты — тариф и место подтвердим по телефону.</span>
                    </p>
                    <form class="form-grid" id="registration-form" action="api/submit.php" method="post">
                        <label>
                            Имя
                            <input type="text" name="name" required autocomplete="name">
                        </label>
                        <label>
                            Телефон
                            <input type="tel" name="phone" required autocomplete="tel">
                        </label>
                        <label>
                            E-mail
                            <input type="email" name="email" required autocomplete="email">
                        </label>
                        <label>
                            Для чего вам курс? Достаточно пары слов
                            <textarea name="purpose" required placeholder="Например: хочу уметь помочь близким"></textarea>
                        </label>
                        <button type="submit" class="btn">Отправить заявку</button>
                    </form>
                    <p class="form-message" id="form-message" aria-live="polite"></p>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <h2 class="section-title">О курсе</h2>
                <p class="section-lead">
                    Это не лекция «для галочки», а полноценный практический день, где каждый блок
                    заканчивается отработкой навыков под контролем инструктора.
                </p>
                <div class="features-grid">
                    <article class="feature-card">
                        <h3>8 часов практики</h3>
                        <p>Больше половины времени посвящено тренировкам на манекенах, имитации травм и работе в парах.</p>
                    </article>
                    <article class="feature-card">
                        <h3>Формат offline</h3>
                        <p>Живое общение, мгновенная обратная связь и возможность задать вопросы по вашим реальным ситуациям.</p>
                    </article>
                    <article class="feature-card">
                        <h3>Сертификат</h3>
                        <p>После курса вы получите именной сертификат и чек-лист действий для дома, работы и поездок.</p>
                    </article>
                    <article class="feature-card">
                        <h3>Малые группы</h3>
                        <p>До 14 человек в группе, чтобы каждый успел отработать все ключевые навыки несколько раз.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <h2 class="section-title">Программа курса</h2>
                <p class="section-lead program-intro">
                    Каждый модуль включает объяснение, демонстрацию инструктора и вашу практику.
                    Вот что вы отработаете за день.
                </p>
                <div class="program-list">
                    <article class="program-module">
                        <h3>10:00 – 11:00 · Оценка обстановки и безопасность</h3>
                        <p>
                            Научитесь оценивать опасность, проверять сознание и дыхание, вызывать скорую. Отработаете последовательность действий на манекене и в паре.
                        </p>
                    </article>
                    <article class="program-module">
                        <h3>11:00 – 12:30 · Сердечно-легочная реанимация</h3>
                        <p>
                            Отработаете СЛР взрослому на манекене: компрессии, искусственное дыхание и смену спасателя. Потренируетесь пользоваться учебным автоматическим дефибриллятором.
                        </p>
                    </article>
                    <article class="program-module">
                        <h3>12:30 – 13:30 · Обед и разбор кейсов</h3>
                        <p>
                            Перерыв на обед и обсуждение случаев из практики инструкторов. Разберёте вопросы после утренних занятий и действия, которые помогают справиться с растерянностью.
                        </p>
                    </article>
                    <article class="program-module">
                        <h3>13:30 – 15:00 · Кровотечения и шок</h3>
                        <p>
                            Потренируетесь накладывать давящую повязку и использовать турникет на имитаторах ран. Разберёте признаки шока и помощь до приезда медиков.
                        </p>
                    </article>
                    <article class="program-module">
                        <h3>15:00 – 16:30 · Переломы, вывихи, ожоги</h3>
                        <p>
                            Отработаете фиксацию конечности подручными средствами и помощь при ожогах. Узнаете, когда нельзя перемещать пострадавшего и каких ошибок избегать.
                        </p>
                    </article>
                    <article class="program-module">
                        <h3>16:30 – 18:00 · Итоговая практика и сертификация</h3>
                        <p>
                            Пройдёте сценарии с несколькими пострадавшими и ограниченным временем. Получите обратную связь инструктора, ответы на вопросы и сертификат.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <h2 class="section-title">Виды травм и состояний</h2>
                <p class="section-lead">
                    На курсе мы разбираем самые частые состояния, с которыми сталкиваются очевидцы
                    в городе, дома, на работе и в путешествиях.
                </p>
                <div class="injury-grid">
                    <article class="injury-card">
                        <h3>Кровотечения</h3>
                        <p>Артериальные, венозные и капиллярные кровотечения, давящая повязка, турникет, контроль после остановки крови.</p>
                    </article>
                    <article class="injury-card">
                        <h3>Переломы и вывихи</h3>
                        <p>Признаки перелома, иммобилизация, транспортная шина, ошибки при перемещении пострадавшего.</p>
                    </article>
                    <article class="injury-card">
                        <h3>Ожоги</h3>
                        <p>Термические и химические ожоги, охлаждение, стерильная повязка, когда нельзя снимать одежду с места ожога.</p>
                    </article>
                    <article class="injury-card">
                        <h3>Обмороки и шок</h3>
                        <p>Признаки шока, положение тела, контроль дыхания, согревание, что нельзя давать пострадавшему.</p>
                    </article>
                    <article class="injury-card">
                        <h3>Остановка дыхания</h3>
                        <p>СЛР, работа в паре, использование автоматического дефibrиллятора, действия до приезда скорой.</p>
                    </article>
                    <article class="injury-card">
                        <h3>Травмы головы и позвоночника</h3>
                        <p>Подозрение на травму шеи и спины, когда нельзя менять положение, фиксация головы и ожидание медиков.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section legal-section">
            <div class="container">
                <h2 class="section-title">Юридические аспекты</h2>
                <p class="section-lead">
                    Многие люди боятся помогать из-за страха юридической ответственности. На курсе
                    мы отдельно разбираем, что говорит закон и как действовать правильно.
                </p>
                <div class="legal-block">
                    <h3>Добросовестный помощник</h3>
                    <p>
                        В России действует принцип добросовестного помощника: если вы помогаете пострадавшему
                        в разумных пределах своих возможностей и без грубой неосторожности, закон защищает
                        вас от необоснованных претензий.
                    </p>
                </div>
                <div class="legal-block">
                    <h3>Границы ответственности</h3>
                    <p>
                        Мы разбираем, какие действия считаются разумными, когда нужно дождаться медиков,
                        и как фиксировать обстоятельства происшествия, если вы оказались свидетелем или участником помощи.
                    </p>
                </div>
                <div class="legal-block">
                    <h3>Документирование и вызов служб</h3>
                    <p>
                        Как правильно передать информацию диспетчеру, что сообщить медикам по прибытии и
                        какие данные важно сохранить для дальнейшего разбирательства или страхового случая.
                    </p>
                </div>
            </div>
        </section>

        <section class="emotional-photo">
            <img src="images/cpr-training.jpg" alt="Практика сердечно-легочной реанимации на манекене">
        </section>

        <section class="section">
            <div class="container">
                <h2 class="section-title">Инструкторы</h2>
                <p class="section-lead">
                    Курс ведут специалисты с многолетним опытом работы в экстренной медицине и обучения.
                </p>
                <div class="instructors-grid">
                    <article class="instructor-card">
                        <div class="instructor-photo">АК</div>
                        <h3>Алексей Кравцов</h3>
                        <p class="instructor-title">Врач скорой медицинской помощи, стаж 14 лет</p>
                        <ul class="credentials">
                            <li>Более 8 000 выездов бригады скорой помощи</li>
                            <li>Сертификат European Resuscitation Council BLS</li>
                            <li>Автор программы «Первая помощь дома и на работе»</li>
                        </ul>
                    </article>
                    <article class="instructor-card">
                        <div class="instructor-photo">МС</div>
                        <h3>Марина Соколова</h3>
                        <p class="instructor-title">Инструктор РКК, фельдшер, стаж 11 лет</p>
                        <ul class="credentials">
                            <li>Обучила более 1 200 слушателей базовой первой помощи</li>
                            <li>Член региональной команды инструкторов РКК</li>
                            <li>Специализация: помощь детям и подросткам</li>
                        </ul>
                    </article>
                    <article class="instructor-card">
                        <div class="instructor-photo">ДН</div>
                        <h3>Дмитрий Новиков</h3>
                        <p class="instructor-title">Парамедик, наставник учебного центра</p>
                        <ul class="credentials">
                            <li>Сертификат ERC First Aid Provider</li>
                            <li>Опыт работы в корпоративных программах безопасности</li>
                            <li>Ведёт практические блоки по кровотечениям и травмам</li>
                        </ul>
                    </article>
                </div>
            </div>
        </section>

        <section class="section pricing-section" id="pricing">
            <div class="container">
                <h2 class="section-title">Тарифы</h2>
                <p class="section-lead">
                    Выберите формат участия. Во все тарифы входят материалы, сертификат и доступ к памятке после курса.
                </p>
                <div class="pricing-grid">
                    <article class="pricing-card">
                        <h3>Базовый</h3>
                        <div class="price">4 900 ₽</div>
                        <ul class="pricing-list">
                            <li>Участие в полном однодневном курсе</li>
                            <li>Сертификат и памятка</li>
                            <li>Кофе-брейки</li>
                        </ul>
                        <button type="button" class="btn btn-register">Записаться</button>
                    </article>
                    <article class="pricing-card featured">
                        <h3>Расширенный</h3>
                        <div class="price">7 900 ₽</div>
                        <ul class="pricing-list">
                            <li>Всё из базового тарифа</li>
                            <li>Набор перевязочных материалов</li>
                            <li>Дополнительный практический блок</li>
                        </ul>
                        <button type="button" class="btn btn-register">Записаться</button>
                    </article>
                    <article class="pricing-card">
                        <h3>Корпоративный</h3>
                        <div class="price">12 900 ₽</div>
                        <ul class="pricing-list">
                            <li>Индивидуальный разбор рисков профессии</li>
                            <li>Консультация для HR или руководителя</li>
                            <li>Отчёт о прохождении для работодателя</li>
                        </ul>
                        <button type="button" class="btn btn-register">Записаться</button>
                    </article>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            © 2026 Учебный центр «Спаси-Себя». Курс первой помощи, Москва.
        </div>
    </footer>

    <!-- Вставьте свой счётчик Яндекс.Метрики здесь -->
</body>
</html>
