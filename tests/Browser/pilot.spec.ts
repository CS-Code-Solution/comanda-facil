import { expect, test, type Browser, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';

const fixture = JSON.parse(
    readFileSync('storage/framework/testing/pilot-fixture.json', 'utf8'),
);
const login = async (browser: Browser, role: string) => {
    const page = await browser.newPage({
        viewport: { width: 390, height: 844 },
        baseURL: process.env.PILOT_URL ?? 'http://127.0.0.1:18096',
    });
    await page.goto(
        (process.env.PILOT_URL ?? 'http://127.0.0.1:18096') + '/login',
    );
    await page.locator('#username').fill('pilot.' + role);
    await page.locator('#pin').fill('048269');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await page.waitForURL('**/dashboard');
    return page;
};
const choose = async (page: Page) => {
    await page.getByLabel('Buscar no cardápio').fill('Calabresa');
    const card = page
        .locator('[data-slot="card"]')
        .filter({ has: page.getByText('Pizza calabresa', { exact: true }) });
    await card.getByRole('button', { name: 'Escolher apresentação' }).click();
    const options = await card
        .locator('select')
        .first()
        .locator('option')
        .evaluateAll((nodes) =>
            nodes.map((node) => {
                if (!(node instanceof HTMLOptionElement))
                    throw new Error('Apresentação inválida');
                return { value: node.value, label: node.textContent ?? '' };
            }),
        );
    await card
        .locator('select')
        .first()
        .selectOption(
            options.find((option) => option.label.includes('53,00'))!.value,
        );
    await card.getByRole('button', { name: 'Adicionar à seleção' }).click();
};

test('piloto: QR, retirada, entrega, pagamento, reconexão, permissões e térmica', async ({
    browser,
    page,
}) => {
    const endpoint = new URL(process.env.PILOT_URL ?? 'http://127.0.0.1:18096');
    expect(['127.0.0.1', 'localhost']).toContain(endpoint.hostname);
    const readiness = await page.request.get('/ready');
    expect(readiness.headers()['x-pilot-test']).toBe('isolated');
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const orderIds: number[] = [];
    for (const mode of ['qr', 'retirada', 'entrega']) {
        await page.goto(mode === 'qr' ? fixture.qr : '/' + mode);
        await choose(page);
        if (mode !== 'qr')
            await page
                .getByLabel('Telefone com DDD', { exact: true })
                .fill('(86) 99900-1122');
        if (mode === 'entrega') {
            for (const [label, value] of Object.entries({
                'Nome de quem recebe': 'Pessoa QA',
                CEP: '64000-000',
                'Rua / avenida': 'Rua QA',
                'Número (ou s/n)': '10',
                Bairro: 'Centro',
                Cidade: 'Cidade QA',
                UF: 'PI',
            })) {
                await page.getByLabel(label, { exact: true }).fill(value);
            }
        }
        await page
            .getByRole('button', { name: 'Conferir pedido e total' })
            .click();
        const confirmed = page.waitForResponse(
            (response) =>
                response.request().method() === 'GET' &&
                response.url().includes('/pedidos/') &&
                response
                    .headers()
                    ['content-type']?.includes('application/json'),
        );
        await page
            .getByRole('button', { name: 'Confirmar e enviar pedido' })
            .click();
        const receipt = await (await confirmed).json();
        await expect(
            page.getByRole('heading', { name: 'Acompanhar pedido' }),
        ).toBeVisible();
        orderIds.push(receipt.props.order.number);
        await expect(
            page.getByText('Atualização em tempo real conectada', {
                exact: false,
            }),
        ).toBeVisible();
        await expect
            .poll(() =>
                page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
            )
            .toBe(true);
    }
    const kitchen = await login(browser, 'cozinha');
    for (const id of orderIds) {
        await kitchen.goto('/operacao/pedidos/' + id);
        await expect(kitchen.locator('body')).not.toContainText('Rua QA');
        await kitchen
            .getByRole('button', { name: 'Marcar Em preparo', exact: true })
            .click();
        await kitchen.getByRole('button', { name: /^Marcar Pronto/ }).click();
        await expect(
            kitchen.getByRole('button', { name: /^Marcar Pronto/ }),
        ).toHaveCount(0);
    }
    const admin = await login(browser, 'gestor');
    admin.on('pageerror', (error) => errors.push(error.message));
    kitchen.on('pageerror', (error) => errors.push(error.message));
    // All three accounts are paid through the actual UI using the three manual methods.
    await admin.goto('/operacao/pedidos/' + orderIds[0]);
    await admin
        .getByRole('button', { name: 'Marcar Servido', exact: true })
        .click();
    await expect(
        admin.getByRole('button', { name: 'Marcar Servido', exact: true }),
    ).toHaveCount(0);
    await admin.goto('/salao/mesas/' + fixture.table);
    await admin
        .getByRole('link', {
            name: 'Receber pagamento / ver conta',
            exact: true,
        })
        .click();
    await admin.getByRole('button', { name: 'Conferir pagamento', exact: true }).click();
    await admin
        .getByLabel('Forma de pagamento', { exact: true })
        .selectOption('card');
    await admin
        .getByRole('button', {
            name: 'Registrar pagamento integral',
            exact: true,
        })
        .click();
    await expect(
        admin.getByRole('link', {
            name: 'Ver registro de pagamento',
            exact: true,
        }),
    ).toBeVisible();
    await admin.goto('/operacao/pedidos/' + orderIds[1]);
    await admin
        .getByRole('link', { name: 'Abrir conta no caixa', exact: true })
        .click();
    await admin.getByRole('button', { name: 'Conferir pagamento', exact: true }).click();
    await admin
        .getByLabel('Forma de pagamento', { exact: true })
        .selectOption('pix');
    await admin
        .getByRole('button', {
            name: 'Registrar pagamento integral',
            exact: true,
        })
        .click();
    await expect(
        admin.getByRole('link', {
            name: 'Ver registro de pagamento',
            exact: true,
        }),
    ).toBeVisible();
    const pickupAccount = admin.url();
    await admin.goto('/operacao/pedidos/' + orderIds[1]);
    await admin
        .getByRole('button', { name: 'Marcar Retirado', exact: true })
        .click();
    await expect(
        admin.getByRole('button', { name: 'Marcar Retirado', exact: true }),
    ).toHaveCount(0);
    const deliveryId = orderIds[2];
    await admin.goto('/entregas/pedidos/' + deliveryId);
    await admin
        .getByLabel('Entregador ativo do restaurante')
        .selectOption(String(fixture.courier));
    await admin
        .getByLabel('Motivo', { exact: true })
        .fill('Atribuição de teste integrado');
    await admin
        .getByRole('button', { name: 'Salvar atribuição', exact: true })
        .click();
    await expect(
        admin.getByText('Entregador: Piloto QA entregador', { exact: true }),
    ).toBeVisible();
    await admin
        .getByRole('button', { name: 'Aguardando entregador', exact: true })
        .click();
    await expect(
        admin
            .locator('[data-slot="card-title"]')
            .filter({ hasText: 'Aguardando entregador' }),
    ).toBeVisible();
    const courier = await login(browser, 'entregador');
    courier.on('pageerror', (error) => errors.push(error.message));
    await courier.goto('/entregas/pedidos/' + deliveryId);
    await courier
        .getByRole('button', { name: 'Saiu para entrega', exact: true })
        .click();
    await courier
        .getByRole('button', { name: 'Entregue', exact: true })
        .click();
    await expect(
        courier.getByText('Registre o pagamento integral', { exact: false }),
    ).toBeVisible();
    await courier
        .getByRole('link', {
            name: 'Conta e pagamento no recebimento',
            exact: true,
        })
        .click();
    await courier
        .getByRole('button', {
            name: /Conferir pagamento|Conferir e registrar|Conferir conta/,
        })
        .click();
    await courier
        .getByLabel('Dinheiro recebido (R$)', { exact: true })
        .fill('100,00');
    await courier
        .getByRole('button', {
            name: 'Registrar pagamento integral',
            exact: true,
        })
        .click();
    await expect(
        courier.getByText('Troco: R$ 40,00', { exact: false }),
    ).toBeVisible();
    await courier.goto('/entregas/pedidos/' + deliveryId);
    await page.context().setOffline(true);
    await courier
        .getByRole('button', { name: 'Entregue', exact: true })
        .click();
    await page.context().setOffline(false);
    await expect(
        page.getByText('Entregue', { exact: true }).first(),
    ).toBeVisible();
    await page.reload();
    await expect(
        page.getByText('Entregue', { exact: true }).first(),
    ).toBeVisible();
    // Cancel a paid pickup and record an actually-performed synthetic refund.
    await admin.goto('/operacao/pedidos/' + orderIds[1]);
    await admin
        .locator('summary')
        .filter({ hasText: 'Cancelar pedido' })
        .click();
    await admin
        .getByLabel('Motivo obrigatório do cancelamento')
        .fill('Cancelamento sintético após pagamento');
    await admin
        .getByRole('button', { name: 'Confirmar cancelamento', exact: true })
        .click();
    await expect(
        admin.getByRole('button', {
            name: 'Confirmar cancelamento',
            exact: true,
        }),
    ).toHaveCount(0);
    await admin.goto(pickupAccount);
    await admin.getByLabel('Forma efetiva da devolução').selectOption('pix');
    await admin.getByLabel('Valor devolvido (R$)').fill('53,00');
    await admin
        .getByLabel('Motivo da devolução')
        .fill('Devolução sintética conferida no ensaio');
    await admin
        .getByLabel(
            'Confirmo que a devolução já foi realizada fora do sistema',
            { exact: true },
        )
        .check();
    await admin
        .getByRole('button', { name: 'Registrar devolução', exact: true })
        .click();
    await expect(
        admin.getByRole('heading', {
            name: 'Histórico de devoluções',
            exact: true,
        }),
    ).toBeVisible();
    await admin.goto('/salao/mesas/' + fixture.table);
    await admin
        .getByRole('button', { name: 'Encerrar atendimento', exact: true })
        .click();
    await expect(
        admin.getByRole('button', {
            name: 'Encerrar atendimento',
            exact: true,
        }),
    ).toHaveCount(0);
    await admin.goto('/caixa/turnos/' + fixture.shift);
    await admin
        .getByLabel('Dinheiro contado (R$)', { exact: true })
        .fill('160,00');
    await admin.getByLabel('Pix conferido (R$)', { exact: true }).fill('0,00');
    await admin
        .getByLabel('Cartão conferido (R$)', { exact: true })
        .fill('53,00');
    await admin
        .getByRole('button', { name: 'Confirmar fechamento', exact: true })
        .click();
    await expect(
        admin.getByRole('button', {
            name: 'Confirmar fechamento',
            exact: true,
        }),
    ).toHaveCount(0);
    await admin.goto('/relatorios/turnos/' + fixture.shift);
    await expect(
        admin.getByRole('heading', { name: 'Entrega', exact: true }),
    ).toBeVisible();
    await expect(
        admin.getByText('Resumo preservado no fechamento do turno.', {
            exact: false,
        }),
    ).toBeVisible();
    expect((await kitchen.goto('/admin/operacao'))!.status()).toBe(403);
    await admin.goto('/admin/operacao');
    await expect(
        admin.getByRole('heading', { name: 'Operação e piloto', exact: true }),
    ).toBeVisible();
    await admin.screenshot({
        path: 'storage/framework/testing/pilot-health.png',
        fullPage: true,
    });
    await admin.goto('/impressoes');
    await admin
        .getByRole('link', { name: /Abrir comprovante #/ })
        .first()
        .click();
    for (const paper of ['58', '80']) {
        await admin
            .getByLabel('Largura da prévia de impressão')
            .selectOption(paper);
        await admin.emulateMedia({ media: 'print' });
        const width = await admin
            .locator('.thermal-ticket')
            .evaluate((element) => element.getBoundingClientRect().width);
        expect(width).toBeCloseTo(((Number(paper) - 6) * 96) / 25.4, 0);
        expect(
            await admin
                .locator('.thermal-ticket')
                .evaluate(
                    (element) => element.scrollWidth <= element.clientWidth + 1,
                ),
        ).toBe(true);
        const pdf = await admin.pdf({ preferCSSPageSize: true });
        expect(pdf.length).toBeGreaterThan(1000);
        if (paper === '58')
            await admin.screenshot({
                path: 'storage/framework/testing/pilot-thermal.png',
                fullPage: true,
            });
        await admin.emulateMedia({ media: 'screen' });
    }
    expect(errors).toEqual([]);
    await kitchen.close();
    await courier.close();
    await admin.close();
});
