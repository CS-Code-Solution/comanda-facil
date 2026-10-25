import { Label } from '@/components/ui/label';
export type Paper = '58' | '80' | 'a4';
export function PaperChoice({
    paper,
    setPaper,
}: {
    paper: Paper;
    setPaper: (paper: Paper) => void;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor="receipt-paper">
                Largura da prévia de impressão
            </Label>
            <select
                id="receipt-paper"
                className="w-full rounded border bg-background p-2"
                value={paper}
                onChange={(event) => setPaper(event.target.value as Paper)}
            >
                <option value="58">Térmica 58 mm</option>
                <option value="80">Térmica 80 mm</option>
                <option value="a4">A4</option>
            </select>
            <p className="text-sm">
                Selecione também o papel no driver. A prévia não confirma saída
                em papel; valide o modelo e o corte na impressora real.
            </p>
        </div>
    );
}
export function ThermalStyle({ paper }: { paper: Paper }) {
    const width = paper === '58' ? 58 : 80;
    return (
        <style>
            {paper === 'a4'
                ? '@media print { @page {size:A4;margin:8mm} .print-controls,.cash-controls {display:none!important} body {background:white!important} }'
                : '@media print { @page {size:' +
                  width +
                  'mm 297mm;margin:3mm} .print-controls,.cash-controls {display:none!important} html,body {background:white!important;margin:0!important;padding:0!important;min-width:0!important} main {margin:0!important;padding:0!important;width:auto!important} .thermal-ticket {width:' +
                  (width - 6) +
                  'mm!important;max-width:100%!important;margin:0!important;padding:0!important;border:0!important} .thermal-ticket,.thermal-ticket * {font-size:11px!important;line-height:1.3!important;overflow-wrap:anywhere!important;color:black!important;box-shadow:none!important} .thermal-ticket [data-slot=card-content],.thermal-ticket [data-slot=card-header] {padding:2mm!important} .thermal-ticket li {break-inside:avoid} }'}
        </style>
    );
}
