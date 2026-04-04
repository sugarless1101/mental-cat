// @ts-check
import { test, expect } from '@playwright/test';

// ===== [3-3] 猫アニメーション =====
test('[3-3] 気分選択前は #cat が非表示（opacity:0）', async ({ page }) => {
    await page.goto('/');
    const cat = page.locator('#cat');
    await expect(cat).toBeAttached();
    const opacity = await cat.evaluate(el => el.style.opacity);
    expect(opacity).toBe('0');
});

test('[3-3] 気分選択後に #cat が表示され cat--happy クラスが付く（good）', async ({ page }) => {
    await page.route('**/api/chat', async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                reply: 'こんにゃ。',
                mood_guess: 'good',
                bgm_key: 'calm',
                tasks: { todo: [], done_recent: [] },
                messages: [],
            }),
        });
    });

    await page.goto('/');
    await page.locator('[data-mood="good"]').click();
    // opacity が 1 になる
    const cat = page.locator('#cat');
    await expect(cat).toHaveCSS('opacity', '1');
    // cat--happy クラスが付いている（mood_guess=good → happy）
    await expect(cat).toHaveClass(/cat--happy/);
});

test('[3-3] mood_guess=bad で cat--sad クラスが付く', async ({ page }) => {
    await page.route('**/api/chat', async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                reply: 'そっかにゃ…',
                mood_guess: 'bad',
                bgm_key: 'sleep',
                tasks: { todo: [], done_recent: [] },
                messages: [],
            }),
        });
    });

    await page.goto('/');
    await page.locator('[data-mood="bad"]').click();
    const cat = page.locator('#cat');
    await expect(cat).toHaveCSS('opacity', '1');
    await expect(cat).toHaveClass(/cat--sad/);
});

test('[3-3] mood_guess=neutral で cat--calm クラスが付く', async ({ page }) => {
    await page.route('**/api/chat', async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                reply: 'にゃ。',
                mood_guess: 'neutral',
                bgm_key: 'calm',
                tasks: { todo: [], done_recent: [] },
                messages: [],
            }),
        });
    });

    await page.goto('/');
    await page.locator('[data-mood="neutral"]').click();
    const cat = page.locator('#cat');
    await expect(cat).toHaveCSS('opacity', '1');
    await expect(cat).toHaveClass(/cat--calm/);
});

test('[3-3] チャット送信後に mood_guess に合わせてクラスが更新される', async ({ page }) => {
    let callCount = 0;
    await page.route('**/api/chat', async route => {
        callCount++;
        if (callCount === 1) {
            // __start__ 呼び出し
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    ok: true,
                    reply: 'こんにゃ。',
                    mood_guess: 'good',
                    bgm_key: 'calm',
                    tasks: { todo: [], done_recent: [] },
                    messages: [],
                }),
            });
        } else {
            // 通常チャット：気分が変わった
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    ok: true,
                    reply: '少し疲れたんだにゃ…',
                    mood_guess: 'bad',
                    bgm_key: 'sleep',
                    tasks: { todo: [], done_recent: [] },
                    messages: [],
                }),
            });
        }
    });

    await page.goto('/');
    await page.locator('[data-mood="good"]').click();
    const cat = page.locator('#cat');
    await expect(cat).toHaveClass(/cat--happy/);

    // チャット送信
    await page.locator('#message').fill('実は疲れた');
    await page.locator('#send-btn').click();
    // mood_guess=bad に更新 → cat--sad
    await expect(cat).toHaveClass(/cat--sad/);
});

// ===== [3-1] Admin ページ =====
test('[3-1] /admin が 200 で表示される（local 環境）', async ({ page }) => {
    const res = await page.goto('/admin');
    expect(res?.status()).toBe(200);
    await expect(page.locator('body')).toContainText('LLM');
});

test('[3-1] /admin に LLM 統計テーブルが含まれる', async ({ page }) => {
    await page.goto('/admin');
    // トークン・コスト・成功率などの文字が含まれる
    await expect(page.locator('body')).toContainText('トークン');
    await expect(page.locator('body')).toContainText('成功率');
});

// ===== [3-2] フィードバック UI =====
test('[3-2] ゲスト時はフィードバックボタンが表示されない', async ({ page }) => {
    await page.route('**/api/chat', async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                reply: 'にゃ。',
                mood_guess: 'neutral',
                bgm_key: 'calm',
                chat_message_id: null,
                tasks: { todo: [], done_recent: [] },
                messages: [],
            }),
        });
    });

    await page.goto('/');
    await page.locator('[data-mood="neutral"]').click();
    await page.waitForTimeout(500);
    // ゲストは IS_AUTH=false → fb-btn ボタンが存在しない
    const fbBtns = page.locator('.fb-btn');
    await expect(fbBtns).toHaveCount(0);
});

// ===== トップページ基本動作（回帰） =====
test('ランディングページの基本要素が揃っている', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/Mental Cat/);
    await expect(page.locator('#mood-panel')).toBeVisible();
    await expect(page.locator('#chat-form')).toBeVisible();
    // サイドバーは md: responsive で条件表示
    await expect(page.locator('.task_list')).toBeAttached();
    await expect(page.locator('.recommend_list')).toBeAttached();
    await expect(page.locator('#cat')).toBeAttached();
});

test('気分ボタン 3 つすべて存在する', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('[data-mood="good"]')).toBeVisible();
    await expect(page.locator('[data-mood="neutral"]')).toBeVisible();
    await expect(page.locator('[data-mood="bad"]')).toBeVisible();
});
