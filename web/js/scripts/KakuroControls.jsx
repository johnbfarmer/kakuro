import React from 'react';
import { ButtonGroup, Button, Glyphicon } from 'react-bootstrap';

export default class KakuroControls extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            savedGameName: '',
            grids: [],
        };

        this.loadVals(props);

        this.updateSavedGameName = this.updateSavedGameName.bind(this);
        this.changeGrid = this.changeGrid.bind(this);
        this.save = this.save.bind(this);
    }

    componentDidMount() {}

    componentWillUpdate(props) {
        this.loadVals(props);
    }

    loadVals(props) {
        this.state.savedGameName = props.savedGameName;
        this.state.grids = this.processDropdownOptions(props.grids);
    }

    updateSavedGameName(e) {
        var val = e.target.value;
        this.setState({savedGameName: val});
    }

    save() {
        this.props.save(this.state.savedGameName);
    }

    processDropdownOptions(data) {
        var arr = [];
        for (var i = 0; i < data.length; i++) {
            var option = data[i];
            var label = 'label' in option ? option.label : option.name;
            var val = option.name;
            arr.push(<option key={i} value={val}>{label}</option>);
          }

        return arr;
    }

    changeGrid(e) {
        this.props.getGrid(e.target.value);
    }

    render() {
        return (
            <div>
                <div className="row">
                    <input value={this.state.savedGameName} onChange={this.updateSavedGameName} />
                    <ButtonGroup>
                        <Button onClick={this.save} title="save">
                            <Glyphicon glyph="floppy-disk" />
                        </Button>
                    </ButtonGroup>
                </div>
                <div className="row">
                    <select onChange={this.changeGrid}>
                        {this.state.grids}
                    </select>
                </div>

            </div>
        );
    }
}

