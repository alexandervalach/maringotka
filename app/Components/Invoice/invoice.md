<!-- Invoice presentation template. HTML blocks are valid Markdown; tables preserve Dompdf's A4 layout. -->
<table class="header"><tr>
<td class="header-logo"><img class="logo" src="{{logo}}" alt="Maringotka na Závadke"></td>
<td class="header-details">
# FAKTÚRA
<table class="meta">
<tr><td class="meta-label">Číslo faktúry:</td><td class="meta-value">{{number}}</td></tr>
<tr><td class="meta-label">Dátum vystavenia:</td><td class="meta-value">{{issued_date}}</td></tr>
<tr><td class="meta-label">Dátum splatnosti:</td><td class="meta-value">{{due_date}}</td></tr>
<tr><td class="meta-label">Dátum pobytu:</td><td class="meta-value">{{stay_dates}}</td></tr>
</table>
</td></tr></table>
<div class="green-band"></div>
<table class="parties"><tr>
<td><div class="party">
## DODÁVATEĽ
<div class="party-content">{{supplier}}</div>
</div></td>
<td><div class="party">
## ODBERATEĽ
<div class="party-content">{{customer}}</div>
</div></td>
</tr></table>
<section class="stay">
## INFORMÁCIE O POBYTE
<p>Maringotka na Závadke{{guest_info}} · Počet nocí: {{nights}}</p>
</section>
<table class="items">
<thead><tr><th>Popis</th><th class="number">Počet osôb</th><th class="number">Počet nocí / ks</th><th class="number">Cena (€)</th><th class="number">Spolu (€)</th></tr></thead>
<tbody>{{rows}}</tbody>
</table>
<div class="total-wrap"><div class="total">CELKOM (€): {{total}}</div></div>
