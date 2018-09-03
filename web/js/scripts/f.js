export const adjustLabels = (idx, cells, h, w) => {
    var isDataCell = cells[idx].is_data;
    // walk up to nearest non-data
    var i = idx - w;
    var nbrs = [];
    while (i > 0) {
        if (!(cells[i].is_data)) {
            var topNonDataNbrIdx = i;
            break;
        } else {
            nbrs.push(cells[i]);
        }
        i = i - w;
    }

    var stripSum = 0;
    var sumIsValid = true;
    if (nbrs.length > 0) {
        nbrs.forEach((c) => {
            if (c.choices.length !== 1) {
                sumIsValid = false;
                stripSum = 0;
            }
            if (sumIsValid) {
                stripSum += c.choices[0];
            }
        });
    }

    if (!isDataCell) {
        if (sumIsValid) {
            cells[topNonDataNbrIdx].display[0] = stripSum;
            stripSum = 0;
        } else {
            sumIsValid = true;
        }
        topNonDataNbrIdx = idx;
    }

    if (sumIsValid) {
        if (isDataCell) {
            stripSum += cells[idx].choices[0];
        }
        // walk down to nearest non-data
        i = idx + w;
        nbrs = [];
        while (i < h * w) {
            if (!(cells[i].is_data)) {
                break;
            } else {
                nbrs.push(cells[i]);
            }
            i += w;
        }

        if (nbrs.length > 0) {
            nbrs.forEach((c) => {
                if (c.choices.length !== 1) {
                    sumIsValid = false;
                    stripSum = 0;
                }
                if (sumIsValid) {
                    stripSum += c.choices[0];
                }
            });
        }

        if (sumIsValid) {
            cells[topNonDataNbrIdx].display[0] = stripSum;
        }
    }

    // horizontal

    // walk left to nearest non-data
    i = idx - 1;
    nbrs = [];
    while (i % w !== w - 1) { // walk until you have wrapped
        if (!(cells[i].is_data)) {
            var leftNonDataNbrIdx = i;
            break;
        } else {
            nbrs.push(cells[i]);
        }
        i = i - 1;
    }

    stripSum = 0;
    sumIsValid = true;
    if (nbrs.length > 0) {
        nbrs.forEach((c) => {
            if (c.choices.length !== 1) {
                sumIsValid = false;
                stripSum = 0;
            }
            if (sumIsValid) {
                stripSum += c.choices[0];
            }
        });
    }

    if (!isDataCell) {
        if (sumIsValid) {
            cells[leftNonDataNbrIdx].display[1] = stripSum;
            stripSum = 0;
        } else {
            sumIsValid = true;
        }
        leftNonDataNbrIdx = idx;
    }

    if (sumIsValid) {
        if (isDataCell) {
            stripSum += cells[idx].choices[0];
        }
        // walk right to nearest non-data
        i = idx + 1;
        nbrs = [];
        while (i % w) {
            if (!(cells[i].is_data)) {
                break;
            } else {
                nbrs.push(cells[i]);
            }
            i += 1;
        }

        if (nbrs.length > 0) {
            nbrs.forEach((c) => {
                if (c.choices.length !== 1) {
                    sumIsValid = false;
                    stripSum = 0;
                }
                if (sumIsValid) {
                    stripSum += c.choices[0];
                }
            });
        }

        if (sumIsValid) {
            cells[leftNonDataNbrIdx].display[1] = stripSum;
        }
    }

    return cells;
}



export const getCellArray = (h, w) => {
    var c = [];
    for (var i = 0; i < h * w; i++) {
        if (i < w || !(i % w)) {
            c.push({choices: [], is_editable: false, is_data: false});
        } else {
            c.push({choices: [1,2,3,4,5,6,7,8,9], is_editable: true});
        }
    }

    return c;
}