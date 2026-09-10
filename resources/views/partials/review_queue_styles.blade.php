@once
<style>
    .review-queue-page {
        --queue-primary: #087fb9;
        --queue-primary-dark: #12384d;
        --queue-border: #dfe9ee;
        --queue-muted: #6b7d8d;
    }
    .review-queue-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 22px;
    }
    .review-queue-heading__copy {
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 0;
    }
    .review-queue-heading__icon {
        width: 54px;
        height: 54px;
        flex: 0 0 54px;
        display: grid;
        place-items: center;
        border-radius: 16px;
        color: #fff;
        background: linear-gradient(145deg, #0ea5e9, #0877ae);
        box-shadow: 0 9px 22px rgba(2, 132, 199, .2);
        font-size: 21px;
    }
    .review-queue-heading__icon--leave {
        background: linear-gradient(145deg, #10b981, #087750);
        box-shadow: 0 9px 22px rgba(16, 185, 129, .2);
    }
    .review-queue-eyebrow {
        display: block;
        margin-bottom: 2px;
        color: var(--queue-primary);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
    }
    .review-queue-eyebrow--leave { color: #087750; }
    .review-queue-heading h1 {
        margin: 0;
        color: var(--queue-primary-dark);
        font-size: clamp(1.3rem, 2vw, 1.7rem);
        font-weight: 800;
    }
    .review-queue-heading p {
        margin: 3px 0 0;
        color: var(--queue-muted);
        font-size: 13px;
    }
    .review-queue-heading__actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }
    .review-queue-date {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 12px;
        border: 1px solid var(--queue-border);
        border-radius: 999px;
        color: #60717e;
        background: #fff;
        font-size: 12px;
        font-weight: 700;
    }
    .review-queue-alert {
        border: 0;
        border-radius: 13px;
        box-shadow: 0 5px 16px rgba(15, 23, 42, .05);
    }
    .review-queue-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 14px;
        padding: 16px 18px;
        border: 1px solid var(--queue-border);
        border-radius: 15px;
        background: linear-gradient(135deg, #fff 0%, #f4fbfe 100%);
    }
    .review-queue-summary__copy {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }
    .review-queue-summary__icon {
        width: 39px;
        height: 39px;
        flex: 0 0 39px;
        display: grid;
        place-items: center;
        border-radius: 11px;
        color: var(--queue-primary);
        background: #e8f7fd;
    }
    .review-queue-summary h2 {
        margin: 0;
        color: #233d4c;
        font-size: 15px;
        font-weight: 800;
    }
    .review-queue-summary p {
        margin: 2px 0 0;
        color: var(--queue-muted);
        font-size: 11.5px;
    }
    .review-queue-count {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: 0 0 auto;
        padding: 7px 11px;
        border-radius: 999px;
        color: #0877ae;
        background: #e6f6fd;
        font-size: 11px;
        font-weight: 800;
    }
    .review-queue-count strong { font-size: 15px; }
    .review-queue-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .review-queue-item {
        display: grid;
        grid-template-columns: 48px minmax(0, 1fr) minmax(155px, auto) auto;
        align-items: center;
        gap: 15px;
        padding: 16px 18px;
        border: 1px solid var(--queue-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 5px 18px rgba(15, 23, 42, .045);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .review-queue-item:hover {
        transform: translateY(-1px);
        border-color: #bddce9;
        box-shadow: 0 9px 24px rgba(15, 23, 42, .07);
    }
    .review-queue-item__icon {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 13px;
        color: #0877ae;
        background: #e9f7fd;
        font-size: 17px;
    }
    .review-queue-item__icon--leave { color: #087750; background: #e5f8ef; }
    .review-queue-item__main { min-width: 0; }
    .review-queue-item__eyebrow {
        display: flex;
        align-items: center;
        gap: 7px;
        flex-wrap: wrap;
        margin-bottom: 4px;
        color: #71808d;
        font-size: 10.5px;
        font-weight: 700;
    }
    .review-queue-type {
        padding: 3px 7px;
        border-radius: 999px;
        color: #516776;
        background: #edf3f6;
    }
    .review-queue-item h3 {
        overflow: hidden;
        margin: 0 0 6px;
        color: #172f3d;
        font-size: 14.5px;
        font-weight: 800;
        line-height: 1.45;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .review-queue-item h3 a { color: inherit; text-decoration: none; }
    .review-queue-item h3 a:hover { color: var(--queue-primary); }
    .review-queue-item__meta {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        color: #70808d;
        font-size: 11px;
    }
    .review-queue-item__meta span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .review-queue-item__meta i { margin-right: 5px; color: #93a4af; }
    .review-queue-item__state { text-align: right; }
    .review-queue-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 800;
        white-space: nowrap;
    }
    .review-queue-status::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }
    .review-queue-status--gray { color: #526271; background: #edf2f5; }
    .review-queue-status--amber { color: #a85b08; background: #fff3d5; }
    .review-queue-status--blue { color: #0877ae; background: #e5f5fc; }
    .review-queue-status--cyan { color: #087f8e; background: #e5f9fa; }
    .review-queue-status--purple { color: #7c3fb0; background: #f3eafb; }
    .review-queue-status--green { color: #087750; background: #e3f8ee; }
    .review-queue-status--red { color: #c63131; background: #fdeaea; }
    .review-queue-item__action {
        min-width: 100px;
        padding: 8px 13px;
        border: 1px solid #acd8e9;
        border-radius: 10px;
        color: #0877ae;
        background: #f2fbfe;
        font-size: 11.5px;
        font-weight: 800;
        text-align: center;
        text-decoration: none;
        transition: color .18s ease, background-color .18s ease, border-color .18s ease;
    }
    .review-queue-item__action:hover { color: #fff; border-color: var(--queue-primary); background: var(--queue-primary); }
    .review-queue-item__action--secondary { color: #526271; border-color: #d5e0e5; background: #fff; }
    .review-queue-empty {
        padding: 60px 20px;
        border: 1px dashed #cbdde5;
        border-radius: 17px;
        color: var(--queue-muted);
        background: #fbfdfe;
        text-align: center;
    }
    .review-queue-empty__icon {
        width: 64px;
        height: 64px;
        display: inline-grid;
        place-items: center;
        margin-bottom: 15px;
        border-radius: 19px;
        color: #087750;
        background: #e4f8ee;
        font-size: 25px;
    }
    .review-queue-empty h2 { margin: 0 0 5px; color: #29404e; font-size: 17px; font-weight: 800; }
    .review-queue-empty p { margin: 0; font-size: 12.5px; }

    @media (max-width: 991.98px) {
        .review-queue-item { grid-template-columns: 44px minmax(0, 1fr) auto; }
        .review-queue-item__state { grid-column: 2; text-align: left; }
        .review-queue-item__action { grid-column: 3; grid-row: 1 / span 2; }
    }
    @media (max-width: 767.98px) {
        .review-queue-heading { align-items: flex-start; flex-direction: column; }
        .review-queue-heading__icon { width: 46px; height: 46px; flex-basis: 46px; border-radius: 14px; font-size: 18px; }
        .review-queue-heading__actions { width: 100%; justify-content: space-between; }
        .review-queue-summary { align-items: flex-start; }
        .review-queue-item { grid-template-columns: 42px minmax(0, 1fr); gap: 11px; padding: 14px; }
        .review-queue-item__icon { width: 40px; height: 40px; }
        .review-queue-item__state { grid-column: 2; }
        .review-queue-item__action { grid-column: 2; grid-row: auto; width: 100%; }
        .review-queue-item__meta { align-items: flex-start; flex-direction: column; gap: 3px; }
        .review-queue-date { display: none; }
    }
</style>
@endonce
