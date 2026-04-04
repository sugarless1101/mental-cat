// @ts-check
import { test, expect } from '@playwright/test';

// ===== D-3: Vite 移行 =====
test('D-3: Tailwind CDN が使われていない（Vite ビルドから読み込まれている）', async ({ page }) => {
    const cdnRequests = [];
    page.on('request', req => {
        if (req.url().includes('cdn.tailwindcss.com')) {
            cdnRequests.push(req.url());
        }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    expect(cdnRequests).toHaveLength(0);

    // Vite ビルドの CSS が読み込まれている
    const viteCss = page.locator('link[rel="stylesheet"][href*="/build/assets/"]');
    await expect(viteCss).toHaveCount(1);
});

// ===== D-4: Chart.js npm 化 =====
test('D-4: Chart.js CDN が使われていない', async ({ page }) => {
    const cdnRequests = [];
    page.on('request', req => {
        if (req.url().includes('cdn.jsdelivr.net') && req.url().includes('chart.js')) {
            cdnRequests.push(req.url());
        }
    });

    // ダッシュボードは認証が必要なためトップページで確認
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    expect(cdnRequests).toHaveLength(0);
});

// ===== D-6: .small CSS 動作確認 =====
test('D-6: 気分ボタン選択後に mood-panel に .small が付与される', async ({ page }) => {
    // AI呼び出しをモック（OpenAI API不要にする）
    await page.route('**/api/chat', async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                reply: 'こんにゃ。',
                mood_guess: 'neutral',
                bgm_key: 'calm',
                tasks: { todo: [], done_recent: [] },
                messages: [],
            }),
        });
    });

    await page.goto('/');
    const moodPanel = page.locator('#mood-panel');

    // 初期状態: .small がない
    await expect(moodPanel).not.toHaveClass(/small/);

    // 気分ボタンをクリック
    await page.locator('[data-mood="neutral"]').click();

    // .small が付与される
    await expect(moodPanel).toHaveClass(/small/);

    // 縮小後の位置スタイルが適用されている
    const position = await moodPanel.evaluate(el => getComputedStyle(el).position);
    expect(position).toBe('absolute');
});

// ===== D-1/D-2: BGM 切替 =====
test('D-1/D-2: 気分選択後に BGM iframe の src が bgm_key に応じて切り替わる', async ({ page }) => {
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

    // iframe src が calm の YouTube embed URL に変わる
    const iframe = page.locator('.recommend_list iframe');
    await expect(iframe).toHaveAttribute('src', /youtube\.com\/embed\/jfKfPfyJRdk/);
});

// ===== D-7: 通常チャットのタスク反映 =====
test('D-7: 通常チャットで AI が提案したタスクが TaskWidget に追加される', async ({ page }) => {
    // 気分選択後の初期状態（タスクなし）
    await page.route('**/api/chat', async route => {
        const body = await route.request().postDataJSON();

        if (body.message === '__start__') {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    ok: true,
                    reply: 'こんにゃ。',
                    mood_guess: 'neutral',
                    bgm_key: 'calm',
                    tasks: { todo: [], done_recent: [] },
                    messages: [],
                }),
            });
        } else {
            // 通常チャットでタスクを返す
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    ok: true,
                    reply: '水を飲んでみるにゃ。',
                    mood_guess: 'neutral',
                    bgm_key: 'calm',
                    tasks: {
                        todo: [{ id: null, title: '水を一杯飲む', status: 'todo' }],
                        done_recent: [],
                    },
                    messages: [],
                }),
            });
        }
    });

    await page.goto('/');
    await page.locator('[data-mood="neutral"]').click();
    await page.waitForTimeout(500);

    // 通常チャットを送信
    await page.locator('#message').fill('今日疲れた');
    await page.locator('#send-btn').click();
    await page.waitForTimeout(1000);

    // TaskWidget にタスクが表示されている
    const taskList = page.locator('#task-items');
    await expect(taskList).toContainText('水を一杯飲む');
});

// ===== ページ基本動作 =====
test('トップページが正常に表示される', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/Mental Cat/);

    // 主要要素が存在する
    await expect(page.locator('#mood-panel')).toBeVisible();
    await expect(page.locator('#chat-form')).toBeVisible();
    // サイドバーは md: responsive で条件表示されるため DOM に存在することを確認
    await expect(page.locator('.task_list')).toBeAttached();
    await expect(page.locator('.recommend_list')).toBeAttached();
});
