import React from 'react';
import PropTypes from 'prop-types';

class Cell extends React.Component {
    constructor(props) {
        super(props);
        this.state = {};
        this.getClasses = this.getClasses.bind(this);
        this.setActive = this.setActive.bind(this);
        this.mouseDown = this.mouseDown.bind(this);
        this.mouseUp = this.mouseUp.bind(this);
    }

    getClasses() {
        var classes = "kakuro-cell";
        let cell = this.props.cell;

        if (cell.col === 0) {
            classes = classes + " clr";
        }

        if (cell.choices.length === 1) {
            classes = classes + " large-num";
        }

        if (cell.active && cell.is_data) {
            return classes + " aktv";
        }

        if (cell.selected) {
            return classes + " selected-cell";
        }

        if (!cell.is_data) {
            let label_v = cell.display[0] ? cell.display[0].toString() : '';
            let label_h = cell.display[1] ? cell.display[1].toString() : '';
            let sum_box = label_h.length > 0 || label_v.length > 0;
            if (this.props.cell.semiactive) {
                if (sum_box) {
                    return classes + " semiactive-sum-box";
                } else {
                    return classes + " semiactive-blnk";
                }
            } else {
                if (cell.active) {
                    classes = classes + " aktv";
                }
                if (sum_box) {
                    if (cell.editing_right) {
                        return classes + " sum-box edit-right";
                    }
                    if (cell.editing) {
                        return classes + " sum-box edit-left";
                    }
                    return classes + " sum-box";
                } else {
                    return classes + " blnk";
                }
            }
        }

        if (cell.error) {
            return classes + " error";
        }

        if (cell.standout) {
            return classes + " standout";
        }

        if (this.props.solved) {
            return classes + " cell-solved";
        }

        if (!cell.active && 'semiactive' in cell && cell.semiactive) {
            return classes + " semiactive";
        }
        
        return classes;
    }

    setActive() {
        this.props.setActive(this.props.cell.row, this.props.cell.col);
    }

    mouseDown() {
        this.props.mouseDown(this.props.cell.row, this.props.cell.col);
    }

    mouseUp() {
        this.props.mouseUp(this.props.cell.row, this.props.cell.col);
    }

    render() {
        if (this.props.cell.is_data) {
            return (
                <div
                    className={this.getClasses()}
                    onClick={() => this.setActive()}
                    onMouseDown={this.mouseDown}
                    onMouseUp={this.mouseUp}
                >
                    <span className='choice-box'>{this.props.cell.choices.join('')}</span>
                </div>
            );
        }
        let label_v = this.props.cell.display[0] ? this.props.cell.display[0].toString() : '';
        let label_h = this.props.cell.display[1] ? this.props.cell.display[1].toString() : '';
        return (
            <div
                className={this.getClasses()}
                onClick={() => this.setActive()}
                onMouseDown={this.mouseDown}
                onMouseUp={this.mouseUp}
            >
                <div className='label-v'>{label_v}</div><div className='label-h'>{label_h}</div>
            </div>
        );
    }
}

Cell.propTypes = {
    mouseUp: PropTypes.func,
    mouseDown: PropTypes.func,
}

Cell.defaultProps = {
    mouseUp: () => {},
    mouseDown: () => {},
}

export default Cell;
