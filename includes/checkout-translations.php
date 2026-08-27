<?php

if (!defined('ABSPATH')) exit;

/**
 * Meditrendy checkout and order email translation overrides for WooCommerce.
 */

function meditrendy_checkout_current_language() {
    if (function_exists('meditrendy_core_current_language')) {
        return meditrendy_core_current_language();
    }

    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

    return substr(strtolower((string) $locale), 0, 2);
}

function meditrendy_checkout_order_pay_translation_map() {
    $translations = [
        'lt' => [
            'You are paying for a guest order. Please continue with payment only if you recognize this order.' => 'Mokate už svečio užsakymą. Tęskite mokėjimą tik tuo atveju, jei atpažįstate šį užsakymą.',
            'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our %s.' => 'Jūsų asmens duomenys bus naudojami jūsų užsakymui apdoroti, jūsų naudojimosi šia svetaine patirčiai gerinti ir kitais tikslais, aprašytais mūsų %s.',
        ],
        'lv' => [
            'You are paying for a guest order. Please continue with payment only if you recognize this order.' => 'Jūs maksājat par viesa pasūtījumu. Turpiniet maksājumu tikai tad, ja atpazīstat šo pasūtījumu.',
            'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our %s.' => 'Jūsu personas dati tiks izmantoti, lai apstrādātu jūsu pasūtījumu, uzlabotu jūsu pieredzi šajā tīmekļa vietnē un citiem mērķiem, kas aprakstīti mūsu %s.',
        ],
        'pl' => [
            'You are paying for a guest order. Please continue with payment only if you recognize this order.' => 'Płacisz za zamówienie gościa. Proszę kontynuować płatność tylko wtedy, gdy rozpoznajesz to zamówienie.',
            'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our %s.' => 'Twoje dane osobowe będą użyte do przetworzenia zamówienia, ułatwienia korzystania ze strony internetowej oraz innych celów opisanych w naszej %s.',
        ],
        'et' => [
            'You are paying for a guest order. Please continue with payment only if you recognize this order.' => 'Maksate külalisena esitatud tellimuse eest. Jätkake maksmist ainult siis, kui tunnete selle tellimuse ära.',
            'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our %s.' => 'Teie isikuandmeid kasutatakse teie tellimuse töötlemiseks, teie kasutuskogemuse toetamiseks sellel veebisaidil ja muudel eesmärkidel, mida on kirjeldatud meie %s.',
        ],
    ];

    $language = meditrendy_checkout_current_language();

    return $translations[$language] ?? [];
}

function meditrendy_checkout_latvian_translation_map() {
    return [
        'Checkout' => 'Norēķināšanās',
        'Order summary' => 'Pasūtījuma kopsavilkums',
        'Add a discount code' => 'Pievienot atlaides kodu',
        'Subtotal' => 'Starpsumma',
        'Delivery' => 'Piegāde',
        'Total' => 'Kopā',
        'Including %s VAT' => 'Ieskaitot %s PVN',
        'Contact information' => 'Kontaktinformācija',
        'Log in' => 'Pieslēgties',
        'Email address' => 'E-pasta adrese',
        'Phone' => 'Tālrunis',
        'You are currently checking out as a guest.' => 'Jūs pašlaik noformējat pasūtījumu kā viesis.',
        'Create an account with %s' => 'Izveidot kontu vietnē %s',
        'Shipping address' => 'Piegādes adrese',
        'Country/region' => 'Valsts/reģions',
        'Select a country / region' => 'Izvēlieties valsti/reģionu',
        'First name' => 'Vārds',
        'Last name' => 'Uzvārds',
        'Address' => 'Adrese',
        'City' => 'Pilsēta',
        'Postcode' => 'Pasta indekss',
        'Shipping options' => 'Piegādes iespējas',
        'Enter an address to see your shipping options.' => 'Ievadiet adresi, lai skatītu piegādes iespējas.',
        'Payment options' => 'Maksājuma iespējas',
        'Choose a payment method' => 'Izvēlieties maksājuma veidu',
        'Please select the country' => 'Lūdzu, izvēlieties valsti',
        'Add a note to your order' => 'Pievienot piezīmi pasūtījumam',
        'Place order' => 'Veikt pasūtījumu',
        'Product' => 'Prece',
        'Quantity' => 'Daudzums',
        'Price' => 'Cena',
    ];
}

/**
 * Fill gaps in the official WooCommerce email catalogs.
 *
 * Estonian, Polish, and British English currently cover the installed email
 * templates completely. Lithuanian and Latvian need fallbacks for newer email
 * blocks, fulfillment messages, stock notifications, and recovery emails.
 */
function meditrendy_woocommerce_email_translation_map() {
    $language = meditrendy_checkout_current_language();
    $translations = [
        'A payment gateway was just enabled on %s.' => [
            'lt' => 'Svetainėje %s ką tik įjungtas mokėjimo būdas.',
            'lv' => 'Vietnē %s tikko tika iespējots maksājuma veids.',
        ],
        'An order has been created for you on %1$s. Your order details are below, with a link to make payment when you’re ready: %2$s' => [
            'lv' => 'Vietnē %1$s jums ir izveidots pasūtījums. Zemāk ir pasūtījuma informācija un saite, lai veiktu maksājumu, kad būsiet gatavs: %2$s',
        ],
        'Canceled order: #%s,' => [
            'lt' => 'Atšauktas užsakymas: #%s,',
            'lv' => 'Atcelts pasūtījums: #%s,',
        ],
        'Click here to set your new password.' => [
            'lv' => 'Noklikšķiniet šeit, lai iestatītu jaunu paroli.',
        ],
        'Confirm email address' => [
            'lt' => 'Patvirtinti el. pašto adresą',
            'lv' => 'Apstiprināt e-pasta adresi',
        ],
        'Confirm your email address' => [
            'lt' => 'Patvirtinkite savo el. pašto adresą',
            'lv' => 'Apstipriniet savu e-pasta adresi',
        ],
        'Congratulations on the sale!' => [
            'lv' => 'Apsveicam ar pārdošanu!',
        ],
        'Default block content' => [
            'lt' => 'Numatytasis bloko turinys',
            'lv' => 'Noklusējuma bloka saturs',
        ],
        'Details for order #%s,' => [
            'lt' => 'Užsakymo #%s informacija,',
            'lv' => 'Pasūtījuma #%s informācija,',
        ],
        'Finish checking out' => [
            'lt' => 'Užbaigti užsakymą',
            'lv' => 'Pabeigt pasūtījumu',
        ],
        'Finish checking out:' => [
            'lt' => 'Užbaigti užsakymą:',
            'lv' => 'Pabeigt pasūtījumu:',
        ],
        'Fulfillment summary' => [
            'lt' => 'Siuntos suvestinė',
            'lv' => 'Sūtījuma kopsavilkums',
        ],
        'Good things are heading your way!' => [
            'lt' => 'Geros naujienos jau pakeliui!',
            'lv' => 'Labas ziņas jau ir ceļā!',
        ],
        'Hello,' => [
            'lt' => 'Sveiki,',
            'lv' => 'Labdien,',
        ],
        'Here’s a reminder of what you’ve bought:' => [
            'lt' => 'Primename, ką įsigijote:',
        ],
        'Here’s the latest info we have:' => [
            'lt' => 'Naujausia turima informacija:',
            'lv' => 'Jaunākā pieejamā informācija:',
        ],
        'Hi there,' => [
            'lv' => 'Labdien,',
        ],
        'Howdy %s,' => [
            'lt' => 'Sveiki, %s,',
            'lv' => 'Labdien, %s,',
        ],
        'If this was intentional, you can safely ignore and delete this email.' => [
            'lt' => 'Jei tai padarėte jūs, šį laišką galite ignoruoti ir ištrinti.',
            'lv' => 'Ja to izdarījāt jūs, šo e-pasta ziņojumu varat ignorēt un dzēst.',
        ],
        'If you did not enable this payment gateway, please log in to your site and consider disabling it here:' => [
            'lt' => 'Jei šio mokėjimo būdo neįjungėte, prisijunkite prie svetainės ir išjunkite jį čia:',
            'lv' => 'Ja neiespējojāt šo maksājuma veidu, piesakieties vietnē un atspējojiet to šeit:',
        ],
        "If you didn't request this email, there's nothing to worry about, and you can safely ignore it." => [
            'lt' => 'Jei šio laiško neprašėte, galite jį saugiai ignoruoti.',
            'lv' => 'Ja nepieprasījāt šo e-pasta ziņojumu, varat to droši ignorēt.',
        ],
        'If you didn’t make this request, just ignore this email. If you’d like to proceed, reset your password via the link below:' => [
            'lt' => 'Jei šios užklausos nepateikėte, ignoruokite šį laišką. Jei norite tęsti, atkurkite slaptažodį naudodami toliau pateiktą nuorodą:',
            'lv' => 'Ja neveicāt šo pieprasījumu, ignorējiet šo e-pasta ziņojumu. Ja vēlaties turpināt, atiestatiet paroli, izmantojot tālāk norādīto saiti:',
        ],
        "If you'd like to continue with your purchase, please return to %s and try a different method of payment." => [
            'lt' => 'Jei norite tęsti pirkimą, grįžkite į %s ir išbandykite kitą mokėjimo būdą.',
            'lv' => 'Ja vēlaties turpināt pirkumu, atgriezieties vietnē %s un izmēģiniet citu maksājuma veidu.',
        ],
        'Just to let you know &mdash; we’ve received your order, and it is now being processed.' => [
            'lt' => 'Informuojame, kad gavome jūsų užsakymą ir šiuo metu jį apdorojame.',
            'lv' => 'Informējam, ka esam saņēmuši jūsu pasūtījumu un pašlaik to apstrādājam.',
        ],
        'Leave a review' => [
            'lt' => 'Palikti atsiliepimą',
            'lv' => 'Atstāt atsauksmi',
        ],
        'Leave a review:' => [
            'lt' => 'Palikti atsiliepimą:',
            'lv' => 'Atstāt atsauksmi:',
        ],
        'New order: #%s' => [
            'lt' => 'Naujas užsakymas: #%s',
        ],
        'No tracking information available for this fulfillment at the moment.' => [
            'lt' => 'Šiuo metu šios siuntos sekimo informacijos nėra.',
            'lv' => 'Pašlaik šim sūtījumam nav pieejama izsekošanas informācija.',
        ],
        'Note from the store:' => [
            'lt' => 'Parduotuvės pastaba:',
            'lv' => 'Veikala piezīme:',
        ],
        'Notification to let you know &mdash; order #%1$s belonging to %2$s has been cancelled:' => [
            'lv' => 'Informējam, ka %2$s pasūtījums #%1$s ir atcelts:',
        ],
        "Once you've confirmed that %s is your email address, we'll link any past orders to your account." => [
            'lt' => 'Patvirtinus, kad %s yra jūsų el. pašto adresas, ankstesnius užsakymus susiesime su jūsų paskyra.',
            'lv' => 'Kad būsiet apstiprinājis, ka %s ir jūsu e-pasta adrese, iepriekšējos pasūtījumus sasaistīsim ar jūsu kontu.',
        ],
        'One of your shipments has been removed' => [
            'lt' => 'Viena iš jūsų siuntų pašalinta',
            'lv' => 'Viens no jūsu sūtījumiem ir noņemts',
        ],
        'Order #%1$s (%2$s)' => [
            'lt' => 'Užsakymas #%1$s (%2$s)',
        ],
        'Order failed: #%s,' => [
            'lt' => 'Nepavykęs užsakymas: #%s,',
            'lv' => 'Neizdevies pasūtījums: #%s,',
        ],
        'Order refunded: %s' => [
            'lt' => 'Grąžinti užsakymo pinigai: %s',
            'lv' => 'Atmaksāts pasūtījums: %s',
        ],
        'Partial refund: Order %s' => [
            'lt' => 'Dalinis grąžinimas: užsakymas %s',
            'lv' => 'Daļēja atmaksa: pasūtījums %s',
        ],
        'Payment confirmation pending' => [
            'lt' => 'Laukiama mokėjimo patvirtinimo',
            'lv' => 'Gaida maksājuma apstiprinājumu',
        ],
        'Payment gateway enabled' => [
            'lt' => 'Mokėjimo būdas įjungtas',
            'lv' => 'Maksājuma veids iespējots',
        ],
        'Pick up where you left off' => [
            'lt' => 'Tęskite nuo ten, kur baigėte',
            'lv' => 'Turpiniet no vietas, kur pārtraucāt',
        ],
        'Rate your recent purchases' => [
            'lt' => 'Įvertinkite naujausius pirkinius',
            'lv' => 'Novērtējiet nesenos pirkumus',
        ],
        'Refund & Returns Policy' => [
            'lt' => 'Pinigų grąžinimo ir prekių grąžinimo taisyklės',
            'lv' => 'Atmaksas un preču atgriešanas noteikumi',
        ],
        'Reset your password' => [
            'lt' => 'Atkurti slaptažodį',
            'lv' => 'Atiestatīt paroli',
        ],
        'Shipment Provider' => [
            'lt' => 'Siuntos vežėjas',
            'lv' => 'Sūtījuma pārvadātājs',
        ],
        'Some details of your shipment have recently been updated. This may include tracking information, item contents, or delivery status.' => [
            'lt' => 'Nesen atnaujinta dalis jūsų siuntos informacijos. Tai gali būti sekimo duomenys, siuntos turinys arba pristatymo būsena.',
            'lv' => 'Nesen tika atjaunināta daļa jūsu sūtījuma informācijas. Tā var būt izsekošanas informācija, sūtījuma saturs vai piegādes statuss.',
        ],
        'Sorry, your order on %1$s was unsuccessful. Your order details are below, with a link to try your payment again: %2$s' => [
            'lt' => 'Apgailestaujame, jūsų užsakymas svetainėje %1$s nepavyko. Toliau pateikta užsakymo informacija ir nuoroda, kuria galite bandyti sumokėti dar kartą: %2$s',
            'lv' => 'Diemžēl jūsu pasūtījums vietnē %1$s neizdevās. Zemāk ir pasūtījuma informācija un saite, lai mēģinātu maksāt vēlreiz: %2$s',
        ],
        'Sorry, your order was unsuccessful' => [
            'lt' => 'Apgailestaujame, jūsų užsakymas nepavyko',
            'lv' => 'Diemžēl jūsu pasūtījums neizdevās',
        ],
        'Store Information' => [
            'lt' => 'Parduotuvės informacija',
        ],
        'Thanks again! If you need any help with your order, please contact us at %s.' => [
            'lt' => 'Dar kartą dėkojame! Jei reikia pagalbos dėl užsakymo, susisiekite su mumis: %s.',
            'lv' => 'Vēlreiz paldies! Ja nepieciešama palīdzība saistībā ar pasūtījumu, sazinieties ar mums: %s.',
        ],
        'Thanks for creating an account on %s. Here’s a copy of your user details.' => [
            'lt' => 'Dėkojame, kad sukūrėte paskyrą svetainėje %s. Štai jūsų paskyros duomenų kopija.',
            'lv' => 'Paldies, ka izveidojāt kontu vietnē %s. Šeit ir jūsu konta informācijas kopija.',
        ],
        'Thanks for your order. It’s currently on hold while we confirm your payment via %s.' => [
            'lt' => 'Dėkojame už užsakymą. Jis laikinai sulaikytas, kol patvirtinsime mokėjimą per %s.',
            'lv' => 'Paldies par pasūtījumu. Tas pašlaik ir aizturēts, kamēr apstiprinām maksājumu, izmantojot %s.',
        ],
        'The payment gateway "%1$s" was just enabled on this site: %2$s' => [
            'lt' => 'Šioje svetainėje ką tik įjungtas mokėjimo būdas „%1$s“: %2$s',
            'lv' => 'Šajā vietnē tikko tika iespējots maksājuma veids “%1$s”: %2$s',
        ],
        'This email has been sent to %s' => [
            'lt' => 'Šis laiškas išsiųstas adresu %s',
            'lv' => 'Šis e-pasta ziņojums nosūtīts uz %s',
        ],
        'This link will remain active for %s.' => [
            'lt' => 'Ši nuoroda galios %s.',
            'lv' => 'Šī saite būs aktīva %s.',
        ],
        'To manage your notifications, %s to log in to your account.' => [
            'lt' => 'Norėdami tvarkyti pranešimus, %s ir prisijunkite prie savo paskyros.',
            'lv' => 'Lai pārvaldītu paziņojumus, %s un piesakieties savā kontā.',
        ],
        'To stop receiving these messages, %s to unsubscribe.' => [
            'lt' => 'Norėdami nebegauti šių pranešimų, %s ir atsisakykite jų.',
            'lv' => 'Lai vairs nesaņemtu šos ziņojumus, %s un anulējiet abonementu.',
        ],
        'Track your shipment' => [
            'lt' => 'Sekti siuntą',
            'lv' => 'Izsekot sūtījumu',
        ],
        'Tracking Number' => [
            'lt' => 'Siuntos sekimo numeris',
        ],
        'Tracking URL' => [
            'lt' => 'Siuntos sekimo nuoroda',
        ],
        'Unfortunately, the payment for order #%1$s from %2$s has failed. The order was as follows:' => [
            'lt' => 'Deja, nepavyko apmokėti %2$s užsakymo #%1$s. Užsakymo informacija:',
            'lv' => 'Diemžēl maksājums par %2$s pasūtījumu #%1$s neizdevās. Pasūtījuma informācija:',
        ],
        "Unfortunately, we couldn't complete your order due to an issue with your payment method." => [
            'lt' => 'Deja, užsakymo užbaigti nepavyko dėl pasirinkto mokėjimo būdo problemos.',
            'lv' => 'Diemžēl pasūtījumu neizdevās pabeigt izvēlētā maksājuma veida problēmas dēļ.',
        ],
        'Unsubscribe from checkout recovery emails' => [
            'lt' => 'Atsisakyti priminimų apie nebaigtą užsakymą',
            'lv' => 'Atteikties no atgādinājumiem par nepabeigtu pasūtījumu',
        ],
        'Unsubscribe from checkout recovery emails:' => [
            'lt' => 'Atsisakyti priminimų apie nebaigtą užsakymą:',
            'lv' => 'Atteikties no atgādinājumiem par nepabeigtu pasūtījumu:',
        ],
        'Username: %s.' => [
            'lt' => 'Naudotojo vardas: %s.',
        ],
        'We hope they’ll be back soon! Read more about <a href="https://woocommerce.com/document/managing-orders/">troubleshooting failed payments</a>.' => [
            'lv' => 'Ceram, ka pircējs drīz atgriezīsies! Lasiet vairāk par <a href="https://woocommerce.com/document/managing-orders/">neizdevušos maksājumu problēmu novēršanu</a>.',
        ],
        'We wanted to let you know that one of the previously fulfilled shipments from your order has been removed from our system. This may have been due to a correction or an update in our fulfillment records. Don’t worry — this won’t affect any items you’ve already received.' => [
            'lt' => 'Informuojame, kad viena iš anksčiau įvykdytų jūsų užsakymo siuntų pašalinta iš mūsų sistemos. Tai galėjo nutikti pakoregavus arba atnaujinus siuntimo duomenis. Nesijaudinkite — tai neturės įtakos jau gautoms prekėms.',
            'lv' => 'Informējam, ka viens no iepriekš izpildītajiem jūsu pasūtījuma sūtījumiem ir noņemts no mūsu sistēmas. Tas varēja notikt sūtījuma datu labošanas vai atjaunināšanas dēļ. Neuztraucieties — tas neietekmēs jau saņemtās preces.',
        ],
        'Welcome to %s' => [
            'lv' => 'Laipni lūdzam vietnē %s',
        ],
        'We’d love to know what you thought of the products you ordered. Your review helps other shoppers make better decisions and helps us improve.' => [
            'lt' => 'Norėtume sužinoti jūsų nuomonę apie užsakytas prekes. Jūsų atsiliepimas padeda kitiems pirkėjams apsispręsti ir mums tobulėti.',
            'lv' => 'Vēlamies uzzināt jūsu viedokli par pasūtītajām precēm. Jūsu atsauksme palīdz citiem pircējiem pieņemt labākus lēmumus un mums pilnveidoties.',
        ],
        'We’ll update you once payment has been confirmed. Here’s a summary of your order:' => [
            'lt' => 'Informuosime, kai mokėjimas bus patvirtintas. Štai jūsų užsakymo suvestinė:',
            'lv' => 'Informēsim, kad maksājums būs apstiprināts. Šeit ir jūsu pasūtījuma kopsavilkums:',
        ],
        'We’re getting in touch to let you know that order #%1$s from %2$s has been cancelled.' => [
            'lt' => 'Informuojame, kad %2$s užsakymas #%1$s buvo atšauktas.',
            'lv' => 'Informējam, ka %2$s pasūtījums #%1$s ir atcelts.',
        ],
        'We’re getting in touch to let you know that your order #%1$s has been cancelled.' => [
            'lt' => 'Informuojame, kad jūsų užsakymas #%1$s buvo atšauktas.',
            'lv' => 'Informējam, ka jūsu pasūtījums #%1$s ir atcelts.',
        ],
        'We’re sorry to let you know that your order #%1$s has been cancelled.' => [
            'lt' => 'Apgailestaujame, tačiau jūsų užsakymas #%1$s buvo atšauktas.',
            'lv' => 'Diemžēl jūsu pasūtījums #%1$s ir atcelts.',
        ],
        'We’ve received your order and it’s currently on hold until we can confirm your payment has been processed.' => [
            'lt' => 'Gavome jūsų užsakymą. Jis laikinai sulaikytas, kol patvirtinsime mokėjimą.',
            'lv' => 'Esam saņēmuši jūsu pasūtījumu. Tas pašlaik ir aizturēts, kamēr apstiprinām maksājumu.',
        ],
        'Woo! Some items you purchased are being fulfilled. You can use the below information to track your shipment:' => [
            'lt' => 'Dalis jūsų įsigytų prekių jau siunčiama. Siuntą galite sekti naudodami toliau pateiktą informaciją:',
            'lv' => 'Daļa jūsu iegādāto preču jau tiek nosūtīta. Sūtījumu varat izsekot, izmantojot tālāk sniegto informāciju:',
        ],
        'You can access more details of your order by visiting <a href="%s" target="_blank">My Account > Orders</a>, and selecting the order you wish to see the latest status for.' => [
            'lt' => 'Daugiau užsakymo informacijos rasite skiltyje <a href="%s" target="_blank">Mano paskyra > Užsakymai</a>, pasirinkę norimą užsakymą.',
            'lv' => 'Plašāku pasūtījuma informāciju varat skatīt sadaļā <a href="%s" target="_blank">Mans konts > Pasūtījumi</a>, izvēloties attiecīgo pasūtījumu.',
        ],
        'You can access to more details of your order by visiting My Account > Orders and select the order you wish to see the latest status of the delivery.' => [
            'lt' => 'Daugiau užsakymo informacijos rasite skiltyje Mano paskyra > Užsakymai, pasirinkę norimą užsakymą.',
            'lv' => 'Plašāku pasūtījuma informāciju varat skatīt sadaļā Mans konts > Pasūtījumi, izvēloties attiecīgo pasūtījumu.',
        ],
        'You can access your account area to view orders, change your password, and more via the link below:' => [
            'lt' => 'Naudodami toliau pateiktą nuorodą galite atidaryti paskyrą, peržiūrėti užsakymus, pakeisti slaptažodį ir atlikti kitus veiksmus:',
            'lv' => 'Izmantojot tālāk norādīto saiti, varat atvērt savu kontu, skatīt pasūtījumus, mainīt paroli un veikt citas darbības:',
        ],
        'You have received this message because your e-mail address was used to sign up for stock notifications on our store.' => [
            'lt' => 'Šį pranešimą gavote, nes jūsų el. pašto adresas buvo naudojamas užsiprenumeruoti pranešimus apie prekių likučius mūsų parduotuvėje.',
            'lv' => 'Saņēmāt šo ziņojumu, jo jūsu e-pasta adrese tika izmantota, lai mūsu veikalā pieteiktos paziņojumiem par preču pieejamību.',
        ],
        "You have received this message because your e-mail address was used to sign up for stock notifications on our store. Wasn't you? Please get in touch with us if you keep receiving these messages." => [
            'lt' => 'Šį pranešimą gavote, nes jūsų el. pašto adresas buvo naudojamas užsiprenumeruoti pranešimus apie prekių likučius mūsų parduotuvėje. Jei tai buvote ne jūs ir tokius pranešimus gaunate toliau, susisiekite su mumis.',
            'lv' => 'Saņēmāt šo ziņojumu, jo jūsu e-pasta adrese tika izmantota, lai mūsu veikalā pieteiktos paziņojumiem par preču pieejamību. Ja to nedarījāt jūs un turpināt saņemt šos ziņojumus, sazinieties ar mums.',
        ],
        'Your item is on the way!' => [
            'lt' => 'Jūsų prekė jau pakeliui!',
            'lv' => 'Jūsu prece jau ir ceļā!',
        ],
        "Your items are still in your cart. We've saved everything, so come back when you're ready." => [
            'lt' => 'Jūsų prekės vis dar krepšelyje. Jas išsaugojome, todėl grįžkite, kai būsite pasiruošę.',
            'lv' => 'Jūsu preces joprojām ir grozā. Esam tās saglabājuši, tāpēc atgriezieties, kad būsiet gatavs.',
        ],
        'Your items are still in your cart. We’ve saved everything, so come back when you’re ready.' => [
            'lt' => 'Jūsų prekės vis dar krepšelyje. Jas išsaugojome, todėl grįžkite, kai būsite pasiruošę.',
            'lv' => 'Jūsu preces joprojām ir grozā. Esam tās saglabājuši, tāpēc atgriezieties, kad būsiet gatavs.',
        ],
        'Your order details are as follows:' => [
            'lt' => 'Jūsų užsakymo informacija:',
            'lv' => 'Jūsu pasūtījuma informācija:',
        ],
        'Your order from %s has been partially refunded.' => [
            'lt' => 'Užsakymo iš %s suma iš dalies grąžinta.',
            'lv' => 'Pasūtījuma no %s summa ir daļēji atmaksāta.',
        ],
        'Your order from %s has been refunded.' => [
            'lv' => 'Pasūtījuma no %s summa ir atmaksāta.',
        ],
        'Your shipment has been updated' => [
            'lt' => 'Jūsų siuntos informacija atnaujinta',
            'lv' => 'Jūsu sūtījuma informācija ir atjaunināta',
        ],
        'A note has been added to your order from {site_title}' => [
            'lt' => 'Prie jūsų užsakymo iš {site_title} pridėta pastaba',
            'lv' => 'Jūsu pasūtījumam no {site_title} ir pievienota piezīme',
        ],
        'A shipment from {site_title} order {order_number} has been cancelled' => [
            'lt' => 'Užsakymo {order_number} iš {site_title} siunta atšaukta',
            'lv' => 'Pasūtījuma {order_number} no {site_title} sūtījums ir atcelts',
        ],
        'A shipment from {site_title} order {order_number} has been updated' => [
            'lt' => 'Užsakymo {order_number} iš {site_title} siuntos informacija atnaujinta',
            'lv' => 'Pasūtījuma {order_number} no {site_title} sūtījuma informācija ir atjaunināta',
        ],
        'An item from {site_title} order {order_number} has been fulfilled!' => [
            'lt' => 'Užsakymo {order_number} iš {site_title} prekė jau siunčiama!',
            'lv' => 'Pasūtījuma {order_number} no {site_title} prece jau tiek nosūtīta!',
        ],
        'Details for order #{order_number}' => [
            'lv' => 'Pasūtījuma #{order_number} informācija',
        ],
        'Details for order #{order_number} on {site_title}' => [
            'lv' => 'Pasūtījuma #{order_number} informācija vietnē {site_title}',
        ],
        'Hopefully they’ll be back. Read more about <a href="https://woocommerce.com/document/managing-orders/">troubleshooting failed payments</a>.' => [
            'lv' => 'Ceram, ka pircējs drīz atgriezīsies. Lasiet vairāk par <a href="https://woocommerce.com/document/managing-orders/">neizdevušos maksājumu problēmu novēršanu</a>.',
        ],
        'How was your order from {site_title}?' => [
            'lt' => 'Kaip vertinate savo užsakymą iš {site_title}?',
            'lv' => 'Kā vērtējat savu pasūtījumu no {site_title}?',
        ],
        'If anything looks off or you have questions, feel free to contact our support team.' => [
            'lt' => 'Jei pastebėjote netikslumų arba turite klausimų, susisiekite su mūsų pagalbos komanda.',
            'lv' => 'Ja pamanījāt neprecizitātes vai jums ir jautājumi, sazinieties ar mūsu atbalsta komandu.',
        ],
        'If you have any questions or notice anything unexpected, feel free to reach out to our support team through your account or reply to this email.' => [
            'lt' => 'Jei turite klausimų ar pastebėjote ką nors netikėto, susisiekite su mūsų pagalbos komanda per savo paskyrą arba atsakykite į šį laišką.',
            'lv' => 'Ja jums ir jautājumi vai pamanījāt ko negaidītu, sazinieties ar mūsu atbalsta komandu savā kontā vai atbildiet uz šo e-pasta ziņojumu.',
        ],
        "If you have any questions, reply to this email and we'll help out." => [
            'lt' => 'Jei turite klausimų, atsakykite į šį laišką ir mes padėsime.',
            'lv' => 'Ja jums ir jautājumi, atbildiet uz šo e-pasta ziņojumu, un mēs palīdzēsim.',
        ],
        'If you need any help with your order, please contact us at {store_email}.' => [
            'lt' => 'Jei reikia pagalbos dėl užsakymo, susisiekite su mumis: {store_email}.',
            'lv' => 'Ja nepieciešama palīdzība saistībā ar pasūtījumu, sazinieties ar mums: {store_email}.',
        ],
        'Items from {site_title} order {order_number} have been fulfilled!' => [
            'lt' => 'Užsakymo {order_number} iš {site_title} prekės jau siunčiamos!',
            'lv' => 'Pasūtījuma {order_number} no {site_title} preces jau tiek nosūtītas!',
        ],
        'New order: #{order_number}' => [
            'lv' => 'Jauns pasūtījums: #{order_number}',
        ],
        'Order Cancelled: #{order_number}' => [
            'lt' => 'Užsakymas atšauktas: #{order_number}',
        ],
        'Order Refunded: {order_number}' => [
            'lv' => 'Pasūtījuma summa atmaksāta: {order_number}',
        ],
        'Order cancelled: #{order_number}' => [
            'lv' => 'Pasūtījums atcelts: #{order_number}',
        ],
        'Order failed: #{order_number}' => [
            'lt' => 'Užsakymas nepavyko: #{order_number}',
            'lv' => 'Pasūtījums neizdevās: #{order_number}',
        ],
        'Order refunded: {order_number}' => [
            'lv' => 'Pasūtījuma summa atmaksāta: {order_number}',
        ],
        'Partial refund: Order {order_number}' => [
            'lv' => 'Daļēja atmaksa: pasūtījums {order_number}',
        ],
        'Payment gateway "{gateway_title}" enabled' => [
            'lt' => 'Mokėjimo būdas „{gateway_title}“ įjungtas',
            'lv' => 'Maksājuma veids “{gateway_title}” iespējots',
        ],
        'Please note that couriers may need some time to provide the latest shipping information.' => [
            'lt' => 'Atkreipkite dėmesį, kad vežėjui gali prireikti laiko naujausiai siuntos informacijai pateikti.',
            'lv' => 'Lūdzu, ņemiet vērā, ka pārvadātājam var būt nepieciešams laiks, lai sniegtu jaunāko sūtījuma informāciju.',
        ],
        'Reset your password for {site_title}' => [
            'lt' => 'Atkurkite savo {site_title} paskyros slaptažodį',
            'lv' => 'Atiestatiet sava {site_title} konta paroli',
        ],
        'Still want it?' => [
            'lt' => 'Vis dar norite įsigyti?',
            'lv' => 'Joprojām vēlaties iegādāties?',
        ],
        'Thank you for your in-store purchase' => [
            'lt' => 'Dėkojame, kad pirkote mūsų parduotuvėje',
            'lv' => 'Paldies par pirkumu mūsu veikalā',
        ],
        "Thanks again for shopping with us. If you have any questions, reply to this email and we'll help out." => [
            'lt' => 'Dar kartą dėkojame, kad perkate pas mus. Jei turite klausimų, atsakykite į šį laišką ir mes padėsime.',
            'lv' => 'Vēlreiz paldies, ka iepērkaties pie mums. Ja jums ir jautājumi, atbildiet uz šo e-pasta ziņojumu, un mēs palīdzēsim.',
        ],
        'Thanks again! If you need any help with your order, please contact us at {store_email}.' => [
            'lt' => 'Dar kartą dėkojame! Jei reikia pagalbos dėl užsakymo, susisiekite su mumis: {store_email}.',
            'lv' => 'Vēlreiz paldies! Ja nepieciešama palīdzība saistībā ar pasūtījumu, sazinieties ar mums: {store_email}.',
        ],
        'Your %1$s order #%2$s has been partially refunded' => [
            'lt' => 'Užsakymo #%2$s iš %1$s suma iš dalies grąžinta',
            'lv' => 'Pasūtījuma #%2$s no %1$s summa ir daļēji atmaksāta',
        ],
        'Your %1$s order #%2$s has been refunded' => [
            'lt' => 'Užsakymo #%2$s iš %1$s suma grąžinta',
            'lv' => 'Pasūtījuma #%2$s no %1$s summa ir atmaksāta',
        ],
        'Your in-store purchase #%1$s at %2$s' => [
            'lt' => 'Jūsų pirkinys parduotuvėje #%1$s, %2$s',
            'lv' => 'Jūsu pirkums veikalā #%1$s, %2$s',
        ],
        'Your items are on the way!' => [
            'lt' => 'Jūsų prekės jau pakeliui!',
            'lv' => 'Jūsu preces jau ir ceļā!',
        ],
        'Your order at {site_title} was unsuccessful' => [
            'lt' => 'Jūsų užsakymas svetainėje {site_title} nepavyko',
            'lv' => 'Jūsu pasūtījums vietnē {site_title} neizdevās',
        ],
        'Your order from {site_title} is on hold' => [
            'lt' => 'Jūsų užsakymas iš {site_title} laikinai sulaikytas',
            'lv' => 'Jūsu pasūtījums no {site_title} ir aizturēts',
        ],
        '[{site_title}] Payment gateway "{gateway_title}" enabled' => [
            'lt' => '[{site_title}] Įjungtas mokėjimo būdas „{gateway_title}“',
            'lv' => '[{site_title}] Iespējots maksājuma veids “{gateway_title}”',
        ],
        '[{site_title}]: Order #{order_number} has been cancelled' => [
            'lv' => '[{site_title}]: pasūtījums #{order_number} ir atcelts',
        ],
        "[{site_title}]: You've got a new order: #{order_number}" => [
            'lv' => '[{site_title}]: saņemts jauns pasūtījums: #{order_number}',
        ],
        '[{site_title}]: Your order #{order_number} has been cancelled' => [
            'lt' => '[{site_title}]: jūsų užsakymas #{order_number} atšauktas',
            'lv' => '[{site_title}]: jūsu pasūtījums #{order_number} ir atcelts',
        ],
        'click here' => [
            'lt' => 'spustelėkite čia',
            'lv' => 'noklikšķiniet šeit',
        ],
    ];
    $map = [];

    foreach ($translations as $source => $localized) {
        if (isset($localized[$language])) {
            $map[$source] = $localized[$language];
        }
    }

    return $map;
}

function meditrendy_checkout_translation_map() {
    $order_pay_translations = meditrendy_checkout_order_pay_translation_map();
    $email_translations = meditrendy_woocommerce_email_translation_map();

    if ('lv' === meditrendy_checkout_current_language()) {
        return array_merge(meditrendy_checkout_latvian_translation_map(), $order_pay_translations, $email_translations);
    }

    if ('lt' !== meditrendy_checkout_current_language()) {
        return $order_pay_translations + $email_translations;
    }

    return $order_pay_translations + $email_translations + [
        'Coupon code "%s" has been applied to your cart.' => 'Nuolaidos kodas „%s“ pritaikytas jūsų krepšeliui.',
        'Coupon code "%s" has been removed from your cart.' => 'Nuolaidos kodas „%s“ pašalintas iš jūsų krepšelio.',
        'Including %s VAT' => 'Įskaitant %s PVM',
        'VAT' => 'PVM',
        '(includes %s)' => '(įskaičiuota %s)',
        'Collection from <strong>%s</strong>:' => 'Atsiėmimas iš <strong>%s</strong>:',
        'Collection from %s:' => 'Atsiėmimas iš %s:',
        'Thank you for your order' => 'Dėkojame už užsakymą',
        'Thanks for shopping with us.' => 'Ačiū, kad perkate pas mus.',
        'Thanks again! If you need any help with your order, please contact us at %s.' => 'Dar kartą dėkojame! Jei reikia pagalbos dėl užsakymo, susisiekite su mumis: %s.',
        'Thanks again! If you need any help with your order, please contact us at {store_email}.' => 'Dar kartą dėkojame! Jei reikia pagalbos dėl užsakymo, susisiekite su mumis: {store_email}.',
        'If you need any help with your order, please contact us at {store_email}.' => 'Jei reikia pagalbos dėl užsakymo, susisiekite su mumis: {store_email}.',
        'We look forward to fulfilling your order soon.' => 'Netrukus pradėsime vykdyti jūsų užsakymą.',
        'Hi %s,' => 'Sveiki, %s,',
        'Just to let you know &mdash; we’ve received your order, and it is now being processed.' => 'Informuojame, kad gavome jūsų užsakymą ir šiuo metu jį apdorojame.',
        'Just to let you know — we’ve received your order, and it is now being processed.' => 'Informuojame, kad gavome jūsų užsakymą ir šiuo metu jį apdorojame.',
        'Just to let you know &mdash; we\'ve received your order #%s, and it is now being processed:' => 'Informuojame, kad gavome jūsų užsakymą #%s ir šiuo metu jį apdorojame:',
        'Just to let you know &mdash; we’ve received your order #%s, and it is now being processed:' => 'Informuojame, kad gavome jūsų užsakymą #%s ir šiuo metu jį apdorojame:',
        'Just to let you know &mdash; we\'ve received your order #%s:' => 'Informuojame, kad gavome jūsų užsakymą #%s:',
        'Just to let you know &mdash; we’ve received your order #%s:' => 'Informuojame, kad gavome jūsų užsakymą #%s:',
        'Your order has been received and is now being processed. Your order details are shown below for your reference:' => 'Jūsų užsakymas gautas ir šiuo metu apdorojamas. Užsakymo informacija pateikta žemiau:',
        'Order summary' => 'Užsakymo suvestinė',
        'Order details' => 'Užsakymo informacija',
        'Order #%s' => 'Užsakymas #%s',
        'Order number:' => 'Užsakymo numeris:',
        'Date:' => 'Data:',
        'Product' => 'Produktas',
        'Quantity' => 'Kiekis',
        'Price' => 'Kaina',
        'Subtotal:' => 'Suma:',
        'Shipping:' => 'Pristatymas:',
        'Payment method:' => 'Mokėjimo būdas:',
        'Total:' => 'Viso:',
        'Billing address' => 'Adresas sąskaitai',
        'Shipping address' => 'Pristatymo adresas',
        'Customer details' => 'Pirkėjo informacija',
        'Email:' => 'El. paštas:',
        'Telephone:' => 'Telefonas:',
        'Note:' => 'Pastaba:',
        'Customer note' => 'Pirkėjo pastaba',
        'View order' => 'Peržiūrėti užsakymą',
    ];
}

function meditrendy_translate_woocommerce_checkout_string($translation, $text, $domain) {
    $translations = meditrendy_checkout_translation_map();

    return $translations[$text] ?? $translation;
}

add_filter('gettext_woocommerce', 'meditrendy_translate_woocommerce_checkout_string', 20, 3);

function meditrendy_translate_checkout_privacy_policy_text($text, $type) {
    if ('checkout' !== $type) {
        return $text;
    }

    $source = 'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our %s.';
    $translations = meditrendy_checkout_order_pay_translation_map();

    if (!isset($translations[$source])) {
        return $text;
    }

    $english_text = sprintf($source, '[privacy_policy]');

    if ($english_text !== $text) {
        return $text;
    }

    return sprintf($translations[$source], '[privacy_policy]');
}

add_filter('woocommerce_get_privacy_policy_text', 'meditrendy_translate_checkout_privacy_policy_text', 20, 2);

function meditrendy_translate_woocommerce_email_content($content) {
    if (!is_string($content) || '' === $content) {
        return $content;
    }

    if ('lt' !== meditrendy_checkout_current_language()) {
        return $content;
    }

    $content = str_replace(
        [
            'Just to let you know — we’ve received your order, and it is now being processed.',
            'Just to let you know &mdash; we’ve received your order, and it is now being processed.',
        ],
        'Informuojame, kad gavome jūsų užsakymą ir šiuo metu jį apdorojame.',
        $content
    );

    $content = preg_replace(
        '/Thanks again!\s*If you need any help with your order,\s*please contact us at ([^<.]+(?:\.[^<.]+)*?)\./u',
        'Dar kartą dėkojame! Jei reikia pagalbos dėl užsakymo, susisiekite su mumis: $1.',
        $content
    );

    $content = preg_replace('/Collection from\s+(<strong>)?(.+?)(<\/strong>)?:/u', 'Atsiėmimas iš $1$2$3:', $content);
    $content = str_replace([' VAT)', ' VAT<', ' VAT '], [' PVM)', ' PVM<', ' PVM '], $content);

    return $content;
}

add_filter('woocommerce_mail_content', 'meditrendy_translate_woocommerce_email_content', 30);

function meditrendy_translate_price_display_suffix($value) {
    if (!is_string($value) || '' === trim($value)) {
        return $value;
    }

    $normalized = strtolower(trim($value));

    if ('including {price_including_tax} vat' === $normalized) {
        return 'Įskaitant {price_including_tax} PVM';
    }

    if ('including {price_excluding_tax} vat' === $normalized) {
        return 'Įskaitant {price_excluding_tax} PVM';
    }

    return $value;
}

add_filter('option_woocommerce_price_display_suffix', 'meditrendy_translate_price_display_suffix', 20);

function meditrendy_enqueue_checkout_block_translations() {
    if (is_admin()) {
        return;
    }

    $is_cart_or_checkout = (function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout());

    if (!$is_cart_or_checkout) {
        return;
    }

    meditrendy_add_checkout_block_translation_script('wp-i18n');
}

add_action('wp_enqueue_scripts', 'meditrendy_enqueue_checkout_block_translations', 5);

function meditrendy_reapply_checkout_block_translations() {
    if (is_admin()) {
        return;
    }

    $is_cart_or_checkout = (function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout());

    if (!$is_cart_or_checkout) {
        return;
    }

    foreach ([
        'wc-cart-checkout-base',
        'wc-blocks-checkout',
        'wc-cart-block-frontend',
        'wc-checkout-block-frontend',
    ] as $handle) {
        if (wp_script_is($handle, 'registered')) {
            meditrendy_add_checkout_block_translation_script($handle);
        }
    }
}

add_action('wp_enqueue_scripts', 'meditrendy_reapply_checkout_block_translations', 100);

function meditrendy_add_checkout_block_translation_script($handle) {
    static $fallback_added = false;

    $translations = meditrendy_checkout_translation_map();

    if (empty($translations)) {
        return;
    }

    wp_enqueue_script('wp-i18n');

    if (!wp_script_is($handle, 'registered') && !wp_script_is($handle, 'enqueued')) {
        return;
    }

    $translations = [
        'Coupon code "%s" has been applied to your cart.' => ['Nuolaidos kodas „%s“ pritaikytas jūsų krepšeliui.'],
        'Coupon code "%s" has been removed from your cart.' => ['Nuolaidos kodas „%s“ pašalintas iš jūsų krepšelio.'],
        'Including %s VAT' => ['Įskaitant %s PVM'],
    ];

    wp_add_inline_script(
        $handle,
        'window.wp && window.wp.i18n && window.wp.i18n.setLocaleData(' . wp_json_encode($translations) . ', "woocommerce");',
        'after'
    );

    if ($fallback_added) {
        return;
    }

    $fallback_added = true;

    wp_add_inline_script(
        $handle,
        <<<'JS'
(function () {
    if (window.meditrendyCheckoutVatTranslationReady) {
        return;
    }

    window.meditrendyCheckoutVatTranslationReady = true;

    const translateVatText = (root) => {
        if (!root) {
            return;
        }

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        const nodes = [];

        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        nodes.forEach((node) => {
            const text = node.nodeValue || '';

            if (/^Including\s+(.+?)\s+VAT$/i.test(text.trim())) {
                node.nodeValue = text.replace(/Including\s+(.+?)\s+VAT/i, '\u012eskaitant $1 PVM');
            }
        });
    };

    const run = () => translateVatText(document.body);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    const startObserver = () => {
        if (!document.body) {
            return;
        }

        let scheduled = false;
        const observer = new MutationObserver(() => {
            if (scheduled) {
                return;
            }

            scheduled = true;
            window.requestAnimationFrame(() => {
                scheduled = false;
                run();
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    };

    if (document.body) {
        startObserver();
    } else {
        document.addEventListener('DOMContentLoaded', startObserver);
    }
})();
JS,
        'after'
    );

}
