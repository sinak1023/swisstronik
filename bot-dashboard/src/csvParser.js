const { parse } = require('csv-parse/sync');

// Parses a CSV buffer into { columns, rows }. `rows` is an array of plain
// objects keyed by column name. Falls back to auto-generated column names
// (column_1, column_2, ...) if the file has no header row that csv-parse
// can make sense of.
function parseCsv(buffer) {
  const records = parse(buffer, {
    columns: true,
    skip_empty_lines: true,
    trim: true,
    bom: true,
    relax_column_count: true,
  });

  const columns = records.length > 0 ? Object.keys(records[0]) : [];
  return { columns, rows: records };
}

function isNumericId(value) {
  return /^-?\d+$/.test(String(value).trim());
}

module.exports = { parseCsv, isNumericId };
